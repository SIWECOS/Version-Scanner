<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Webapps\CandidateBuilder;
use App\Webapps\Releases\Releases;
use App\Webapps\SignatureBuilder;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class UpdateDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'svs:updatedatabase';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch CMS releases and update signature file';

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
        $this->info('=== Updating SVS Database ===');
        $this->info('Processing ' . count(config('webapps')) . ' applications');

        $this->info('');
        $this->info('Reading Signature Database');

        $signatures = json_decode(file_get_contents(storage_path('signatures.json')), true);

        $this->info('Done!');
        $this->info('');

        foreach (config('webapps') as $appName => $appConfig) {
            $this->info('--- Processing ' . $appName . ' Packages ---');

            // Update signature file if new app has been added
            if (empty($signatures[$appName])) {
                $signatures[$appName] = [
                    "hashes" => [],
                    "versions" => []
                ];
            }

            /** @var Releases $releaseClass */
            $releaseClassName = 'App\\Webapps\\Releases\\' . $appName;
            $releaseClass = new $releaseClassName;

            // Check if we already have the latest versions for all branches, skip if yes
            $latestVersions = $releaseClass->getLatest();
            $latestMatchCount = 0;

            foreach ($latestVersions as $branch) {
                if (in_array($branch["version"], $signatures[$appName]["versions"])) {
                    $latestMatchCount++;
                }
            }

            if ($latestMatchCount === count($latestVersions)) {
                $this->info('Release data for ' . $appName . ' is up to date, skipping...');
                $this->info('');

                continue;
            }

            // Process packages
            foreach ($releaseClass->getPackages() as $version => $package) {
                // Check if the package is already in the signature files
                if (in_array($version, $signatures[$appName]["versions"])) {
                    $this->info($appName . ' version ' . $version . ' already in DB, skipping...');
                    $this->info('');

                    continue;
                }

                // Download and process
                $httpClient = new Client;

                // Path variable
                $versionPath = $appName . '/' . $version;

                // Make directory and download packages
                Storage::disk('local')->makeDirectory($versionPath);
                $httpClient->get(
                    $package["url"],
                    ['sink' => storage_path('app/' . $versionPath . '/' . $package["filename"])]
                );

                $this->info('Extracting package ' . $package["filename"]);

                // Extract release package
                switch (pathinfo($package["filename"], PATHINFO_EXTENSION)) {
                    case "bz2":
                        exec(
                            "cd " . storage_path('app/' . $versionPath)
                            . "; tar xfvj " . storage_path('app/' . $versionPath . '/' . $package["filename"])
                        );
                        break;

                    case "zip":
                        exec(
                            "cd " . storage_path('app/' . $versionPath)
                            . "; unzip " . storage_path('app/' . $versionPath . '/' . $package["filename"])
                        );
                        break;

                    case "tgz":
                    case "gz":
                        exec(
                            "cd " . storage_path('app/' . $versionPath)
                            . "; tar xfz " . storage_path('app/' . $versionPath . '/' . $package["filename"])
                            . " --strip 1"
                        );
                        break;

                    default:
                        throw new \RuntimeException(
                            "Invalid file extension: " . pathinfo($package["filename"], PATHINFO_EXTENSION)
                        );
                }

                // Build signatures
                $this->info('Building signature for ' . $appName . ' ' . $version);
                $signatureBuilder = new SignatureBuilder(storage_path('app/' . $versionPath), $appConfig);

                foreach ($signatureBuilder->buildSignatures() as $file => $hash) {
                    // Append file to signature list
                    if (empty($signatures[$appName]["hashes"][$file])) {
                        $signatureArray["hashes"][$file] = [];
                    }

                    if (empty($signatures[$appName]["hashes"][$file][$hash])) {
                        $signatures[$appName]["hashes"][$file][$hash] = [];
                    }

                    $signatures[$appName]["hashes"][$file][$hash][] = $version;
                }

                // Remove version folder
                exec("rm -Rf " . storage_path('app/' . $versionPath));

                // Append new version to array
                $signatures[$appName]["versions"][] = $version;
            }

            // Sort version list
            uksort($signatures[$appName]["versions"], 'version_compare');

            $this->info('');
            $this->info('');
        }

        $this->info('Saving updated signature file');
        file_put_contents(storage_path('signatures.json'), json_encode($signatures, JSON_PRETTY_PRINT));

        $this->info('');
        $this->info('');
        $this->info('--- Creating candidates lists ---');

        $candidates = [];

        foreach (config('webapps') as $appName => $appConfig) {
            $this->info('Updating candidates for ' . $appName);

            // Pass hashes and number of versions to builder
            $candidateBuilder = new CandidateBuilder(
                $signatures[$appName]["hashes"],
                $signatures[$appName]["versions"]
            );

            // Get Candidates
            $identifierCandidates = $candidateBuilder->getIdentifierCandidates();
            $versionproofCandidates = $candidateBuilder->getVersionproofCandidates();
            $prooflessVersionCount = count($versionproofCandidates) - count(array_filter($versionproofCandidates));

            // Print info
            $this->info(
                'Found ' . count($identifierCandidates)
                . ' identifier candidates, best score '
                . reset($identifierCandidates)["score"]
            );
            $this->info('Found ' . $prooflessVersionCount . ' versions without proof');
            $this->info('');

            $candidates[$appName] = [
                "identifier" => $identifierCandidates,
                "versionproof" => $versionproofCandidates
            ];
        }

        //Save file
        file_put_contents(storage_path('candidates.json'), json_encode($candidates, JSON_PRETTY_PRINT));
    }
}
