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

        $this->info($result["Versions"] === null ? 'Version: Unknown' : '');

        foreach ($result["Versions"] as $version => $details) {
            $this->info("Version: " . $version);
            $this->info(
                ($details["IsLatest"] === null) ? 'Is Latest: Unknown' : 'Is Latest: ' . ($details["IsLatest"] ? 'true' : 'false')
            );
            $this->info(
                ($details["Latest"] === null) ? 'Latest: Unknown' : 'Latest: ' . ($details["Latest"] ? 'true' : 'false')
            );
            $this->info(
                ($details["Supported"] === null) ? 'Is Supported: Unknown' : 'Is Supported: ' . ($details["Supported"] ? 'true' : 'false')
            );
            $this->info('=======================');
        }
    }
}
