<?php

namespace App\Console\Commands;

use GuzzleHttp\Exception\RequestException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;

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
     * @return mixed
     */
    public function handle()
    {
        $this->info('=== Scanning the website. This could take a few seconds. ===');

        $hasCandidates = $this->fileExists('candidates.json', 'local');

        if (!$hasCandidates)
        {
            $this->info('=== Scan interrupted. No candidates found. ===');

            return false;
        }

        $candidatesFile = $this->retrieveFile('candidates.json');

        if (!is_string($candidatesFile) || $candidatesFile === '')
        {
            $this->info('=== Scan interrupted. Retrieving candidates failed. ===');

            return false;
        }

        $candidates = json_decode($candidatesFile, true);

        if (!is_array($candidates) || !count($candidates))
        {
            $this->info('=== Scan interrupted. Could not read candidates. ===');

            return false;
        }

        $httpClient = new Client;
        $i = 0;
        $j = 0;
        $actualCms = '';
        $tempCms = '';

        foreach($candidates as $cms => $cmsData) {
            $bestCandidates = $this->limitCandidates($cmsData, 15);

            foreach($bestCandidates as $filename => $hashInfo) {
                // Remove first appearance of the slash
                $readableUrl = preg_replace('/^\//', '', $filename);

                try {
                    $this->info('=== Scanning for CMS: '. $cms .' ===');

                    $httpClient->get('https://www.a7layouts.de/' . $readableUrl);

                    // Algorithm to find out which CMS is used by the users website
                    if ($tempCms === '')
                    {
                        $tempCms = $cms;

                        if (!$actualCms)
                        {
                            $actualCms = $cms;
                        }

                        $i++;
                    } else if ($tempCms === $cms) {
                        $i++;
                    } else if ($tempCms !== $cms) {
                        $j++;

                        if ($j > $i)
                        {
                            $tempCms = '';
                            $actualCms = $cms;
                            $i = 0;
                        }
                    }

                } catch (RequestException $e) {
                    $this->info('=== File not found: '. $readableUrl .' ===');

                    continue;
                }
            }
        }

        echo $actualCms;

        return null;
    }

    private function fileExists(string $filename, string $disk): bool
    {
        return Storage::disk($disk)->exists($filename);
    }

    private function retrieveFile(string $filename): string
    {
        return Storage::get($filename);
    }

    public function limitCandidates ($candidates, $limit)
    {
        return array_splice($candidates["identifier"], 0, $limit);
    }
}
