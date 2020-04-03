<?php
declare(strict_types=1);

namespace App\Webapps\Releases;

use GuzzleHttp\Exception\ClientException;

class Typo3 extends Releases
{
    const IGNORES = [
        "4.5.33",
        "4.6.0",
        "4.6.0beta3",
        "4.6.0alpha1"
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
                if (in_array($version, self::IGNORES)) {
                    continue;
                }

                $packages[$version] = [
                    "url" => "https://get.typo3.org" . $versionData["url"]["tar"],
                    "filename" => $version . ".tgz"
                ];
            }
        }

        return $packages;
    }
}
