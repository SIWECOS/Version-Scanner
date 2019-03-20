<?php

namespace App\Console\Commands;

use GuzzleHttp\Exception\RequestException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use GuzzleHttp\Client;

class ScanWebsite extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'svs:version {--website=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Well-defined scanner to get a websites CMS version.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $this->info('=== Scanning the website. This could take a few seconds. ===');

        $hasCandidates = Storage::disk('local')->exists('candidates.json');

        if (!$hasCandidates)
        {
            $this->info('=== Scan interrupted. No candidates found. ===');

            return;
        }

        $contents = Storage::get('candidates.json');

        $candidates = json_decode($contents, true);

        if (!is_array($candidates) || !count($candidates))
        {
            $this->info('=== Scan interrupted. Could not read candidates. ===');

            return;
        }

        $httpClient = new Client;

        $website = $this->option('website');

        $detectedCms = $this->detectCms($candidates, $httpClient, $website);

        if ($detectedCms === '')
        {
            $this->info('=== Scan interrupted. CMS could not be detected. ===');

            return;
        }

        $version = $this->scan($candidates, $detectedCms, $httpClient, $website);

        echo $version;
    }

    public function scan(array $candidates, string $cms, $httpClient, string $website): string
    {
        $possibleVersions = [];

        // Entry point of this scan. Iterates over the main candidates.
        foreach($candidates[$cms]['identifier'] as $filename => $hashInfo) {
            $sourceHashes = $hashInfo['data'];
            $readableUrl = preg_replace('/^\//', '', $filename);

            try {
                $response = $httpClient->get($website . $readableUrl);

                $body = (string) $response->getBody();

                // Hash generation of the requested file from the website.
                $targetHash = md5($body);
            } catch (RequestException $e) {
                $this->info('=== Scan interrupted. File not found. ===');

                continue;
            }

            // First look up, if the candidates hash list contains the generated hash from the website.
            if (!isset($sourceHashes[$targetHash]))
            {
                continue;
            }

            // Count how many versions are related to this hash.
            $amountOfVersions = count($sourceHashes[$targetHash]);

            // If there is only one version, no further searching required
            if ($amountOfVersions === 1)
            {
                return $sourceHashes[$targetHash][0];
            }

            /**
             * Continuing here means that there are more versions related to this hash and -
             * it is impossible to tell which version the CMS has.
             */
            if (!count($possibleVersions))
            {
                // Store those versions and compare them later
                $possibleVersions = $sourceHashes[$targetHash];

                continue;
            }

            // Get the intersection of the current hash tree and the one before
            $possibleVersions = array_intersect($possibleVersions, $sourceHashes[$targetHash]);

            // Check again, if the intersection resulted in one entry. No further searching required then.
            if (count($possibleVersions) === 1)
            {
                return $possibleVersions[0];
            }
        }

        // If greater, the version has not be found yet. Starting a more detailed and specific scan with -
        // the last few versions left to check on.
        if (count($possibleVersions) > 1)
        {
            $mostAccurateVersion = [];

            foreach ($candidates[$cms]['versionproof'] as $versionNumber => $fileInfo) {
                if (in_array($versionNumber, $possibleVersions, true))
                {
                    // This may results into a boolean => false; which means that this version cannot be identified.
                    if (!is_array($fileInfo))
                    {
                        continue;
                    }

                    foreach ($fileInfo as $filename => $hash)
                    {
                         try {
                            $response = $httpClient->get($website . $filename);

                            $body = (string) $response->getBody();

                            $websiteFileHash = md5($body);

                            if (!isset($mostAccurateVersion[$versionNumber]))
                            {
                                $mostAccurateVersion[$versionNumber] = 0;
                            }

                             if ($websiteFileHash !== $hash)
                             {
                                 continue;
                             }

                             // Only count up if both hashes are equally.
                             $mostAccurateVersion[$versionNumber]++;
                        } catch (RequestException $e) {
                            $this->info('=== Scan interrupted. File not found. ===');

                            continue;
                        }
                    }
                }
            }

            asort($mostAccurateVersion);

            return array_key_last($mostAccurateVersion);
        }

        return '';
    }

    public function limitCandidates(array $candidates, int $limit): array
    {
        return array_splice($candidates["identifier"], 0, $limit);
    }

    public function detectCms(array $candidates, $httpClient, string $website): string
    {
        $highestCandidates = [];

        foreach($candidates as $cms => $cmsData) {
            $bestCandidates = $this->limitCandidates($cmsData, 15);

            foreach($bestCandidates as $filename => $hashInfo) {
                // Remove first appearance of the slash
                $readableUrl = preg_replace('/^\//', '', $filename);

                //sleep(1);

                try {
                    $this->info('=== Scanning for CMS: '. $cms .' ===');

                    $httpClient->get($website . $readableUrl);

                    if(!isset($highestCandidates[$cms]))
                    {
                        $highestCandidates[$cms] = 0;
                    }

                    $highestCandidates[$cms]++;

                } catch (RequestException $e) {
                    $this->info('=== File not found: '. $readableUrl .' ===');

                    continue;
                }
            }
        }

        $sorted = asort($highestCandidates);

        if (!$sorted || !count($highestCandidates))
        {
            return '';
        }

        return array_key_last($highestCandidates);
    }
}
