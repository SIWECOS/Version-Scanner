<?php
declare(strict_types=1);

namespace App\Webapps\Releases;

use GuzzleHttp\Exception\ClientException;

class Typo3 extends Releases
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
            $apiResponse = $http->get('https://get.typo3.org/json');

        } catch (ClientException $e) {
            throw new \RuntimeException("Could not fetch latest version");
        }

        $branchList = json_decode((string) $apiResponse->getBody(), true);
        $versions = [];

        // Parse branch information
        foreach ($branchList as $branchName => $branchData) {
            if (!is_array($branchData)) {
                continue;
            }

            $versions[] = [
                "branch" => $branchName,
                "version" => $branchData["latest"],
                "supported" => $branchData["active"]
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
            $apiResponse = $http->get('https://get.typo3.org/json');

        } catch (ClientException $e) {
            throw new \RuntimeException("Could not fetch releases for TYPO3");
        }

        $branchList = json_decode((string) $apiResponse->getBody(), true);
        $packages = [];

        foreach ($branchList as $branch) {
            if (!is_array($branch)) {
                continue;
            }

            foreach ($branch["releases"] as $version => $versionData) {
                $packages[$version] = [
                    "url" => $versionData["url"]["tar"],
                    "filename" => $version . ".tgz"
                ];
            }
        }

        return $packages;
    }
}
