<?php
declare(strict_types = 1);

namespace App;

use App\Webapps\Releases\Releases;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
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
        "Version" => null,
        "IsLatest" => null,
        "Latest" => null,
        "Supported" => null
    ];

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

        // Detect used CMS
        $this->detectCms();
        $this->detectVersion();
        $this->isSupported();

        // Make callbacks
        if (count($this->callbackUrls)) {
            $this->notifyCallbacks();
        }

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
                sleep($this->delay / 1000);

                try {
                    $this->client->get($this->website . $filename);

                    Log::info('=== File found: '. $filename .' ===');

                    if (!isset($matchCount[$cms])) {
                        $matchCount[$cms] = 0;
                    }

                    $matchCount[$cms]++;
                } catch (RequestException $e) {
                    Log::info('=== File not found: '. $filename .' ===');

                    continue;
                }
            }
        }

        if (!count($matchCount)) {
            return;
        }

        arsort($matchCount);
        reset($matchCount);

        $matches = $matchCount[key($matchCount)];

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
        // We can only detect a version if we know the CMS
        if ($this->result["CMS"] === null) {
            return;
        }

        $possibleVersions = [];

        // Entry point of this scan. Iterates over the main candidates.
        foreach ($this->candidates[$this->result["CMS"]]['identifier'] as $filename => $hashInfo) {
            $sourceHashes = $hashInfo['data'];
            $filename = ltrim($filename, '/');

            // Sleep to not overwhelm the server
            sleep($this->delay / 1000);

            try {
                $response = $this->client->get($this->website . $filename);

                $body = (string) $response->getBody();

                // Hash generation of the requested file from the website.
                $targetHash = md5($body);
            } catch (RequestException $e) {
                continue;
            }

            // First look up, if the candidates hash list contains the generated hash from the website.
            if (!isset($sourceHashes[$targetHash])) {
                continue;
            }

            // Count how many versions are related to this hash.
            $amountOfVersions = count($sourceHashes[$targetHash]);

            // If there is only one version, no further searching required
            if ($amountOfVersions === 1) {
                $this->result["Version"] = $sourceHashes[$targetHash][0];

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

            // Check again, if the intersection resulted in one entry. No further searching required then.
            if (count($possibleVersions) === 1) {
                $this->result["Version"] = $possibleVersions[0];

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
                    sleep($this->delay / 1000);

                    try {
                        $response = $this->client->get($this->website . $filename);

                        $body = (string) $response->getBody();

                        $websiteFileHash = md5($body);

                        // We have an exact match, it's the version we are looking for
                        if ($websiteFileHash === $hash) {
                            $this->result["Version"] = $versionNumber;

                            return;
                        }
                    } catch (RequestException $e) {
                        continue;
                    }
                }
            }
        }
    }

    /**
     * Check if the detected version is supported
     *
     * @return void
     */
    protected function isSupported(): void
    {
        // We can only detect a version if we know the CMS
        if ($this->result["CMS"] === null || $this->result["Version"] === null) {
            return;
        }

        // Get latest versions for CMS
        $releaseClassName = 'App\\Webapps\\Releases\\' . $this->result["CMS"];
        /** @var Releases $releaseClass */
        $releaseClass = new $releaseClassName;
        $branches = $releaseClass->getLatest();

        // Try to find the right branch
        foreach ($branches as $branch) {
            if (stripos($this->result["Version"], $branch["branch"]) === 0) {
                Log::info("Found a matching branch " . $branch["branch"] . " for version" . $this->result["Version"]);

                // Compare if we are using a supported version
                $this->result["Supported"] = $branch["supported"];
                $this->result["IsLatest"] = version_compare($this->result["Version"], $branch["version"], '>=');
                $this->result["Latest"] = $branch["version"];

                return;
            }
        }
    }

    /**
     * Send callbacks with SIWECOS report format
     */
    protected function notifyCallbacks(): void
    {
        $score = 100;
        $scoreType = 'info';
        $testDetails = null;

        // Case 1: CMS and Version detected, up to date and supported
        if ($this->result["CMS"] !== null && $this->result["Supported"] && $this->result["IsLatest"]) {
            $testDetails = [
                [
                    "placeholder" => "CMS_UPTODATE",
                    "values" => [
                        "cms" => $this->result["CMS"],
                        "version" => $this->result["Version"]
                    ]
                ]
            ];
        }

        // Case 2: CMS and Version detected but outdated
        if ($this->result["CMS"] !== null && $this->result["Supported"] && $this->result["IsLatest"] === false) {
            $testDetails = [
                [
                    "placeholder" => "CMS_OUTDATED",
                    "values" => [
                        "cms" => $this->result["CMS"],
                        "version" => $this->result["Version"],
                        "latest" => $this->result["Latest"]
                    ]
                ]
            ];

            $score = 0;
            $scoreType = 'warning';
        }

        // Case 3: CMS and Version detected but out of support
        if ($this->result["CMS"] !== null && $this->result["Supported"] === false) {
            $testDetails = [
                [
                    "placeholder" => "CMS_OUT_OF_SUPPORT",
                    "values" => [
                        "cms" => $this->result["CMS"],
                        "version" => $this->result["Version"]
                    ]
                ]
            ];

            $score = 0;
            $scoreType = 'critical';
        }

        // Case 4: CMS found but can't detect version
        if ($this->result["CMS"] !== null && $this->result["Version"] === null) {
            $testDetails = [
                [
                    "placeholder" => "CMS_CANT_DETECT_VERSION",
                    "values" => [
                        "cms" => $this->result["CMS"]
                    ]
                ]
            ];

            $scoreType = 'warning';
        }


        // Case 5: Can't detect CMS
        if ($this->result["CMS"] === null) {
            $testDetails = [
                [
                    "placeholder" => "CMS_CANT_DETECT_CMS"
                ]
            ];

            $scoreType = 'warning';
        }

        $report = [
            'name'         => 'CMSVersion',
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
            try {
                $this->client->post($url, [
                    'http_errors' => false,
                    'timeout'     => 60,
                    'json'        => $report,
                ]);
            } catch (\Exception $e) {
                Log::warning('Could not send the report to the following callback url: '.$url);
            }
        }
    }
}
