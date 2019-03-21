<?php

namespace App\Console\Commands;

use App\VersionScan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

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
    protected $description = 'Get a websites CMS version.';

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

        $hasCandidates = Storage::disk('signatures')->exists('candidates.json');

        if (!$hasCandidates) {
            $this->info('=== Scan interrupted. No candidates found. ===');

            return;
        }

        // Set up scan from request
        $scan = new VersionScan(
            $this->option('website')
        );

        // Execute scan
        $result = $scan->scan();

        $this->info('=== Scan finished! ===');
        $this->info('Results:');

        // Output report
        $this->info(
            ($result["CMS"] === null) ? 'CMS: Unknown' : 'CMS: ' . $result["CMS"]
        );
        $this->info(
            ($result["Version"] === null) ? 'Version: Unknown' : 'Version: ' . $result["Version"]
        );
        $this->info(
            ($result["IsLatest"] === null) ? 'Is Latest: Unknown' : 'Is Latest: ' . $result["IsLatest"]
        );
        $this->info(
            ($result["Latest"] === null) ? 'Latest: Unknown' : 'Latest: ' . $result["Latest"]
        );
        $this->info(
            ($result["Supported"] === null) ? 'Is Supported: Unknown' : 'Is Supported: ' . $result["Supported"]
        );
    }
}
