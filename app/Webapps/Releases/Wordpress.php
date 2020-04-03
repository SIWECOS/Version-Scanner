<?php
declare(strict_types=1);

namespace App\Webapps\Releases;

use GuzzleHttp\Exception\ClientException;

class Wordpress extends Releases
{
    const IGNORES = [
        "1.0.2"
    ];

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
            $apiResponse = $http->get('https://api.wordpress.org/core/version-check/1.7/');
        } catch (ClientException $e) {
            throw new \RuntimeException("Could not fetch latest version");
        }

        $releaseList = json_decode((string) $apiResponse->getBody(), true)["offers"];
        $versions = [];
        $branchList = [];

        // Parse branch information
        foreach ($releaseList as $release) {
            preg_match('/(\d{1,2})\.(\d{1,2})\.?(\d{1,2})?/m', $release["version"], $matches);

            if (!count($matches)) {
                continue;
            }

            $branchName = $matches[1] . "." . $matches[2];

            if (in_array($branchName, $branchList)) {
                continue;
            }

            $branchList[] = $branchName;

            $versions[] = [
                "branch" => $branchName,
                "version" => $release["version"],
                "supported" => true
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
        // Fetch data from
        try {
            $apiResponse = $http->get('https://wordpress.org/download/releases/');
        } catch (ClientException $e) {
            throw new \RuntimeException("Could not fetch releases for TYPO3");
        }

        $releaseMarkup = (string) $apiResponse->getBody();

        preg_match_all(
            '/<a href=\"(https:\/\/wordpress\.org\/wordpress-\d{1,2}\.\d{1,2}(?:\.\d{1,2})?\.zip)/s',
            $releaseMarkup,
            $releaseMatches,
            PREG_SET_ORDER,
            0
        );

        $packages = [];

        foreach ($releaseMatches as $releaseMatch) {
            preg_match('/.*\/wordpress-(\d{1,2}\.\d{1,2}\.\d{1,2})\.zip/s', $releaseMatch[1], $versionMatch);

            if (!count($versionMatch)) {
                throw new \RuntimeException("Invalid WP version string " . $releaseMatch[1]);
            }

            if (in_array($versionMatch[1], self::IGNORES)) {
                continue;
            }

            $packages[$versionMatch[1]] = [
                "url" => $versionMatch[0],
                "filename" => basename($versionMatch[0])
            ];
        }

        return $packages;
    }
}
