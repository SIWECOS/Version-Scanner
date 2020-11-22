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

            if ((string) $release->version_minor === "" && (int) $release->version_major > 7) {
                $branchName = (string) $release->version_major . ".0";
            }

            // Check if we have the latest version
            if (!empty($versions[$branchName])
                && version_compare((string) $release->version, $versions[$branchName]["version"], '<')) {
                continue;
            }

            $supported = false;

            // Hardcode supported version date for 7
            if ($release->security) {
                foreach ($release->security->attributes() as $attributeName => $attributeValue) {
                    if ($attributeName === "covered" && (int) $attributeValue === 1) {
                        $supported = true;

                        break;
                    }
                }
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
