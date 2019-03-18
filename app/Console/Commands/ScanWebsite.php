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
    protected $signature = 'svs:version';

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

        $hasCandidates = $this->fileExists('candidates.json', 'local');

        if (!$hasCandidates)
        {
            $this->info('=== Scan interrupted. No candidates found. ===');

            return;
        }

        $candidatesFile = $this->retrieveFile('candidates.json');

        if (!is_string($candidatesFile) || $candidatesFile === '')
        {
            $this->info('=== Scan interrupted. Retrieving candidates failed. ===');

            return;
        }

        $candidates = json_decode($candidatesFile, true);

        if (!is_array($candidates) || !count($candidates))
        {
            $this->info('=== Scan interrupted. Could not read candidates. ===');

            return;
        }

        $httpClient = new Client;

        $detectedCms = $this->detectCms($candidates, $httpClient);
        //$detectedCms = 'Joomla';

        $version = $this->scan($candidates, $detectedCms, $httpClient);

        echo $version;
    }

    public function scan($candidates, $cms, $httpClient)
    {
        $version = '';
        $temp = [];

        foreach($candidates[$cms]['identifier'] as $filename => $hashInfo) {
            $sourceHash = $hashInfo['data'];
            $readableUrl = preg_replace('/^\//', '', $filename);

            try {
                $response = $httpClient->get('https://www.eco.de/' . $readableUrl);

                $body = (string) $response->getBody();

                $targetHash = md5($body);

                if (isset($sourceHash[$targetHash]))
                {
                    $amountOfVersions = count($sourceHash[$targetHash]);

                    // If there is only one version, no further searching required
                    if ($amountOfVersions === 1)
                    {
                        return $sourceHash[$targetHash][0];
                    }

                    // Unable to detect which version because this file occurrences in more than one version
                    if ($amountOfVersions > 1 && !count($temp))
                    {
                        $temp = $sourceHash[$targetHash];
                    }

                    if ($amountOfVersions > 1 && count($temp))
                    {
                        $occurrences = $this->occurrences($temp, $sourceHash[$targetHash]);

                        if (count($occurrences) === 1)
                        {
                            $version = $occurrences[0];
                        }
                    }
                }
            } catch (RequestException $e) {
                // $this->info('=== Scan interrupted. '. $e->getMessage() .' ===');
            }
        }

        // After this foreach I have to check, if the version is still empty
        // If so, I need a second foreach for versionproof

        return $version;
    }

    public function occurrences($temp, $target): array
    {
        $occurrences = [];

        foreach ($temp as $tValue) {
            foreach ($target as $sValue)
            {
                if ($tValue === $sValue)
                {
                    $occurrences[] = [$tValue];
                }
            }
        }

        return $occurrences;
    }

    private function fileExists(string $filename, string $disk): bool
    {
        return Storage::disk($disk)->exists($filename);
    }

    private function retrieveFile(string $filename): string
    {
        return Storage::get($filename);
    }

    public function limitCandidates($candidates, $limit): array
    {
        return array_splice($candidates["identifier"], 0, $limit);
    }

    public function detectCms(array $candidates, $httpClient): string
    {
        $i = 0;
        $j = 0;
        $result = '';
        $temp = '';

        foreach($candidates as $cms => $cmsData) {
            $bestCandidates = $this->limitCandidates($cmsData, 15);

            foreach($bestCandidates as $filename => $hashInfo) {
                // Remove first appearance of the slash
                $readableUrl = preg_replace('/^\//', '', $filename);

                //sleep(1);

                try {
                    $this->info('=== Scanning for CMS: '. $cms .' ===');

                    $httpClient->get('https://www.eco.de/' . $readableUrl);

                    // Algorithm to find out which CMS is used by the users website
                    if ($temp === '')
                    {
                        $temp = $cms;

                        if (!$result)
                        {
                            $result = $cms;
                        }

                        $i++;
                    } else if ($temp === $cms) {
                        $i++;
                    } else if ($temp !== $cms) {
                        $j++;

                        if ($j > $i)
                        {
                            $temp = '';
                            $result = $cms;
                            $i = 0;
                        }
                    }

                } catch (RequestException $e) {
                    $this->info('=== File not found: '. $readableUrl .' ===');

                    continue;
                }
            }
        }

        return $result;
    }
}
