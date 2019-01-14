<?php
declare(strict_types=1);

namespace App\Webapps\Releases;

use Carbon\Carbon;
use GuzzleHttp\Exception\ClientException;

class Drupal extends Releases
{
    /**
     * Get latest version
     *
     * @return array
     */
    public function getLatest(): array
    {
        $http = $this->getHttp();

        // Fetch data from
        try {
            $apiResponse = $http->get('https://updates.drupal.org/release-history/drupal/all');

        } catch (ClientException $e) {
            throw new \RuntimeException("Could not fetch latest version");
        }

        $releaseList = simplexml_load_string((string) $apiResponse->getBody())->releases;
        $versions = [];

        // Parse branch information
        foreach ($releaseList->children() as $release) {
            // Ignore dev releases
            if (stripos((string) $release->version, "-dev") !== false) {
                continue;
            }

            $branchName = (string) $release->version_major . "." . (string) $release->version_minor;

            if ((string) $release->version_minor === "") {
                $branchName = (string) $release->version_major . ".0";
            }

            // Check if we have the latest version
            if (!empty($versions[$branchName])
                && version_compare((string) $release->version, $versions[$branchName]["version"], '<')) {
                continue;
            }

            $supported = false;

            // Hardcode supported version date for 7
            if ((int) $release->version_major === 7
                && Carbon::now()->diff(Carbon::create(2021, 11, 01))->invert == 0) {
                $supported = true;
            }

            if ($release->terms->count() == 0) {
                continue;
            }

            $versions[$branchName] = [
                "branch" => $branchName,
                "version" => (string) $release->version,
                "supported" => $supported,
                "minor" => (int) $release->version_minor,
                "major" => (int) $release->version_major
            ];
        }

        // Determine support for Drupal >= 8
        foreach ($versions as $branchName => $version) {
            // Determine support for 8 and upwards
            if ((int) $version["major"] >= 8) {
                // Drupal >= 8 supports the latest and previous version
                if (empty($versions[$version["major"] . "." . ($version["minor"] + 1)])) {
                    $versions[$branchName]["supported"] = true;
                }
            }
        }

        return $versions;
    }

    /**
     * Get packages for all versions
     *
     * @return array
     */
    public function getPackages(): array
    {
        $http = $this->getHttp();

        // Fetch data from
        try {
            $apiResponse = $http->get('https://updates.drupal.org/release-history/drupal/all');

        } catch (ClientException $e) {
            throw new \RuntimeException("Could not fetch latest version");
        }

        $releaseList = simplexml_load_string((string) $apiResponse->getBody())->releases;
        $packages = [];

        // Parse branch information
        foreach ($releaseList->children() as $release) {
            // Ignore dev releases
            if (stripos((string) $release->version, "-dev") !== false) {
                continue;
            }

            $packages[(string) $release->version] = [
                "url" => (string) $release->download_link,
                "filename" => basename((string) $release->download_link)
            ];
        }

        return $packages;
    }
}
