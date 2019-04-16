<?php
declare(strict_types = 1);

namespace App;

use App\Webapps\Releases\Releases;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VersionScan
{
    protected $website;
    protected $delay;
    protected $userAgent;
    protected $callbackUrls = [];
    protected $candidates = [];
    protected $client;

    protected $result = [
        "CMS" => null,
        "Versions" => []
    ];

    /**
     * VersionScan constructor.
     *
     * @param string $website
     * @param int    $delay  delay in ms
     * @param array  $callbackUrls
     * @param bool   $userAgent
     */
    public function __construct(string $website, int $delay = 0, array $callbackUrls = [], $userAgent = false)
    {
        // Save website with trailing slash
        $this->website = rtrim($website, '/') . '/';
        $this->delay = $delay;
        $this->callbackUrls = $callbackUrls;
        $this->userAgent = $userAgent;

        $this->client = new Client([
            'headers'         => [
                'User-Agent' => $userAgent,
            ]
        ]);
    }

   /**
    * Execute the job.
    *
    * @return array
    */
    public function scan(): array
    {
        $fileExists = Storage::disk('signatures')->exists('candidates.json');

        // Check if candidates file exists
        if (!$fileExists) {
            throw new \RuntimeException('Could not find candidates.json in storage folder');
        }

        // Read candidates file
        $this->candidates = json_decode(Storage::disk('signatures')->get('candidates.json'), true);

        if (!is_array($this->candidates) || !count($this->candidates)) {
            throw new \RuntimeException('Invalid candidates file');
        }

        Log::info('=== Starting Scan Job for : '. $this->website .' ===');

        // Detect used CMS
        $this->detectCms();
        $this->detectVersion();
        $this->isSupported();

        // Make callbacks
        if (count($this->callbackUrls)) {
            $this->notifyCallbacks();
        }

        Log::info('=== Finishing Scan Job for : '. $this->website .' ===');

        return $this->result;
    }


    /**
     * Detect the CMS used
     *
     * @return void
     */
    protected function detectCms(): void
    {
        $matchCount = [];

        foreach ($this->candidates as $cms => $cmsData) {
            // Get the first 15 files per CMS
            $bestCandidates = array_splice($cmsData["identifier"], 0, 20);

            Log::info('=== Scanning for CMS: '. $cms .' ===');

            foreach ($bestCandidates as $filename => $hashInfo) {
                // Remove lead slashes
                $filename = ltrim($filename, '/');

                // Sleep to not overwhelm the server
                usleep($this->delay * 1000);

                try {
                    $this->client->get($this->website . $filename, [
                        'timeout'     => 5
                    ]);

                    Log::info('=== File found: '. $this->website . $filename .' ===');

                    if (!isset($matchCount[$cms])) {
                        $matchCount[$cms] = 0;
                    }

                    $matchCount[$cms]++;
                } catch (\Exception $e) {
                    Log::info('=== File not found: '. $this->website . $filename .' ===');

                    continue;
                }
            }
        }

        if (!count($matchCount)) {
            return;
        }

        // Check for an edge case: if multiple CMS have the full match count, it's most likely because
        // the server returns 200 status code for 404 pages
        if (count(array_filter($matchCount, function ($count) {
            return ($count === 20);
        })) > 1) {
            Log::info('Returning no CMS because server is misconfigured');

            return;
        }

        // Sort matches array
        arsort($matchCount);
        reset($matchCount);

        $matches = $matchCount[key($matchCount)];

        // If we have less than 4 matches, it's very likely that this is not one of our CMS
        if ($matches < 4) {
            Log::info('Returning no CMS because less than 3 matches');

            return;
        }

        Log::info('Returning '. key($matchCount) .' as CMS with ' . $matches . ' matches');

        $this->result["CMS"] = key($matchCount);
    }

    /**
     * Detect the used version
     *
     * @return void
     */
    public function detectVersion(): void
    {
        Log::info('=== Detecting used Version ===');


        // We can only detect a version if we know the CMS
        if ($this->result["CMS"] === null) {
            return;
        }

        $possibleVersions = [];

        // Entry point of this scan. Iterates over the main candidates.
        foreach ($this->candidates[$this->result["CMS"]]['identifier'] as $filename => $hashInfo) {
            $sourceHashes = $hashInfo['data'];
            $filename = ltrim($filename, '/');

            Log::info('Fetching ' . $filename);

            // Sleep to not overwhelm the server
            usleep($this->delay * 1000);

            try {
                $response = $this->client->get($this->website . $filename, [
                    'timeout'     => 5
                ]);

                $body = (string) $response->getBody();

                // Hash generation of the requested file from the website.
                $targetHash = md5($body);

                Log::info('File ' . $this->website . $filename . ' with hash ' . $targetHash . ' found');
            } catch (\Exception $e) {
                Log::info('File ' . $this->website . $filename . ' not found, next file...');

                continue;
            }

            // First look up, if the candidates hash list contains the generated hash from the website.
            if (!isset($sourceHashes[$targetHash])) {
                Log::info('Unknown hash of ' . $this->website . $filename . ', next file...');

                continue;
            }

            // Count how many versions are related to this hash.
            $amountOfVersions = count($sourceHashes[$targetHash]);

            // If there is only one version, no further searching required
            if ($amountOfVersions === 1) {
                $this->result["Versions"] = [$sourceHashes[$targetHash][0]];

                return;
            }

            /**
             * Continuing here means that there are more versions related to this hash and -
             * it is impossible to tell which version the CMS has.
             */
            if (!count($possibleVersions)) {
                // Store those versions and compare them later
                $possibleVersions = $sourceHashes[$targetHash];

                continue;
            }

            // Get the intersection of the current hash tree and the one before
            $possibleVersions = array_intersect($possibleVersions, $sourceHashes[$targetHash]);

            Log::info('Remaining versions after this file: ' . count($possibleVersions));

            // Check again, if the intersection resulted in one entry. No further searching required then.
            if (count($possibleVersions) === 1) {
                $this->result["Versions"] = [reset($possibleVersions)];

                return;
            }
        }

        // THE single possible has not be found yet. Starting a more detailed and specific scan with -
        // the last few versions left to check on.
        foreach ($this->candidates[$this->result["CMS"]]['versionproof'] as $versionNumber => $fileInfo) {
            if (in_array($versionNumber, $possibleVersions, true)) {
                // This may results into a boolean => false; which means that this version cannot be identified.
                if (!is_array($fileInfo)) {
                    continue;
                }

                foreach ($fileInfo as $filename => $hash) {
                    $filename = ltrim($filename, '/');

                    // Sleep to not overwhelm the server
                    usleep($this->delay * 1000);

                    try {
                        $response = $this->client->get($this->website . $filename, [
                            'timeout'     => 5
                        ]);

                        $body = (string) $response->getBody();

                        $websiteFileHash = md5($body);

                        // We have an exact match, it's the version we are looking for
                        if ($websiteFileHash === $hash) {
                            $this->result["Versions"] = [$versionNumber];

                            return;
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }
        }

        $this->result["Versions"] = $possibleVersions;
    }

    /**
     * Check if the detected version is supported
     *
     * @return void
     */
    protected function isSupported(): void
    {
        // We can only detect a version if we know the CMS
        if ($this->result["CMS"] === null || $this->result["Versions"] === null) {
            return;
        }

        // Get latest versions for CMS
        $releaseClassName = 'App\\Webapps\\Releases\\' . $this->result["CMS"];
        /** @var Releases $releaseClass */
        $releaseClass = new $releaseClassName;
        $branches = $releaseClass->getLatest();

        $detailedVersions = $this->encloseMoreDetailsToVersions($this->result["Versions"]);

        // Try to find the right branch
        foreach ($this->result["Versions"] as $version) {
            $matchedBranch = $this->getBranchForVersion($branches, $version);

            if (count($matchedBranch) > 0) {
                Log::info("Found a matching branch " . $matchedBranch["branch"] . " for version " . $version);

                // Compare if we are using a supported version
                $detailedVersions[$version]["Supported"] = $matchedBranch["supported"];
                $detailedVersions[$version]["IsLatest"] = version_compare($version, $matchedBranch["version"], '>=');
                $detailedVersions[$version]["Latest"] = $matchedBranch["version"];
            }
        }

        $this->result["Versions"] = $detailedVersions;
    }

    /**
     * Adds up some details to each version whether its supported and latest
     *
     * @param array $versions
     *
     * @return array
     */
    protected function encloseMoreDetailsToVersions(array $versions): array
    {
        $moreDetails = [];

        foreach ($versions as $version) {
            $moreDetails[$version] = [
                "IsLatest" => null,
                "Latest" => null,
                "Supported" => null
            ];
        }

        return $moreDetails;
    }

    /**
     * @param array $branches
     * @param string $version
     * @return array
     */
    protected function getBranchForVersion(array $branches, string $version): array
    {
        $matchedBranch = "";

        foreach ($branches as $branch) {
            if (stripos($version, $branch["branch"]) === 0) {
                $matchedBranch = $branch;
            }
        }

        return $matchedBranch;
    }

    /**
     * Send callbacks with SIWECOS report format
     */
    protected function notifyCallbacks(): void
    {
        $score = 100;
        $scoreType = 'success';
        $testDetails = null;

        // Case 1: CMS and Version detected, up to date and supported
        if (count($this->result["Versions"]) === 1) {
            // Key of the version. For instance '3.9.4'
            $versionKey = key($this->result["Versions"]);

            if ($this->result["CMS"] !== null &&
                $this->result["Versions"][$versionKey]["Supported"] &&
                $this->result["Versions"][$versionKey]["IsLatest"]) {
                $testDetails = [
                    [
                        "placeholder" => "CMS_UPTODATE",
                        "values" => [
                            "cms" => $this->result["CMS"],
                            "version" => $versionKey
                        ]
                    ]
                ];
            }
        }

        $outdated = 0;
        $upToDate = 0;
        $unsupported = 0;

        foreach ($this->result["Versions"] as $version => $details) {
            if ($this->result["CMS"] !== null && $details["Supported"] && !$details["IsLatest"]) {
                $outdated++;
            }

            if ($this->result["CMS"] !== null && $details["Supported"] && $details["IsLatest"]) {
                $upToDate++;
            }

            if ($this->result["CMS"] !== null && !$details["Supported"]) {
                $unsupported++;
            }
        }


        // Case 2: CMS and Version detected but outdated
        if ($this->result["CMS"] !== null && $upToDate === 0 && $outdated > 0) {
            $testDetails = [
                [
                    "placeholder" => "CMS_OUTDATED",
                    "values" => [
                        "cms" => $this->result["CMS"],
                        "version" => implode(', ', array_keys($this->result["Versions"])),
                        "latest" => $this->result["Versions"][key($this->result["Versions"])]['Latest']
                    ]
                ]
            ];

            $score = 0;
            $scoreType = 'warning';
        }

        // Case 3: CMS and Version detected but out of support
        if ($this->result["CMS"] !== null && $unsupported > 0 && $upToDate === 0 || $outdated === 0) {
            $testDetails = [
                [
                    "placeholder" => "CMS_OUT_OF_SUPPORT",
                    "values" => [
                        "cms" => $this->result["CMS"],
                        "version" => implode(', ', array_keys($this->result["Versions"]))
                    ]
                ]
            ];

            $score = 0;
            $scoreType = 'critical';
        }

        // Case 4: One version up-to-date; rest outdated
        if ($this->result["CMS"] !== null && $upToDate === 1 && $outdated === 0 && $unsupported === 0) {
            $testDetails = [
                [
                    "placeholder" => "CMS_MIGHT_UPTODATE",
                    "values" => [
                        "cms" => $this->result["CMS"],
                        "version" => implode(', ', array_keys($this->result["Versions"]))
                    ]
                ]
            ];

            $score = 90;
            $scoreType = 'warning';
        }

        // Case 5: CMS found but can't detect version
        if ($this->result["CMS"] !== null && count($this->result["Versions"]) === 0) {
            $testDetails = [
                [
                    "placeholder" => "CMS_CANT_DETECT_VERSION",
                    "values" => [
                        "cms" => $this->result["CMS"]
                    ]
                ]
            ];

            $scoreType = 'info';
        }

        // Case 6: Can't detect CMS
        if ($this->result["CMS"] === null) {
            $testDetails = [
                [
                    "placeholder" => "CMS_CANT_DETECT_CMS"
                ]
            ];

            $scoreType = 'info';
        }

        $report = [
            'name'         => 'CMSVERSION',
            'version'      => file_get_contents(base_path('VERSION')),
            'hasError'     => false,
            'errorMessage' => null,
            'score'        => $score,
            'tests'        => [
                [
                    "name" => "CMSVERSION",
                    "errorMessage" => null,
                    "hasError" => false,
                    "score" => $score,
                    "scoreType" => $scoreType,
                    "testDetails" => $testDetails
                ]
            ],
        ];

        foreach ($this->callbackUrls as $url) {
            Log::info('Making callback for ' . $this->website . ' to '
                . $url . ' with content ' . json_encode($report));

            try {
                $this->client->post($url, [
                    'http_errors' => false,
                    'timeout'     => 60,
                    'json'        => $report,
                ]);
            } catch (\Exception $e) {
                Log::warning('Could not send the report to the following callback url: '.$url);
            }

            Log::info('Finished callback for ' . $this->website);
        }
    }
}
