<?php
declare(strict_types=1);

namespace App\Webapps\Releases;

use GuzzleHttp\Exception\ClientException;

class Joomla extends Releases
{
    const IGNORES = [
        "3.1.3",
        "3.1.2",
        "2.5.12"
    ];

    const SPECIALNAMES = [
        "Joomla-1.5.0.zip",
        "Joomla_1.0.1-Stable.tar.gz",
        "Joomla_1.0.0-Stable.tar.gz"
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
            $apiResponse = $http->get('https://downloads.joomla.org/api/v1/latest/cms');

        } catch (ClientException $e) {
            throw new \RuntimeException("Could not fetch latest version");
        }

        $versions = json_decode((string) $apiResponse->getBody(), true)["branches"];

        // Parse branch information
        foreach ($versions as $branchKey => $versionBranch) {
            // Transform branch name to match our expected format
            $versions[$branchKey]["branch"] = str_ireplace("Joomla! ", "", $versions[$branchKey]["branch"]);

            switch ($versionBranch["branch"]) {
                case "Joomla! 3":
                case "Joomla! 4":
                    $versions[$branchKey]["supported"] = true;
                    break;

                case "Weblinks":
                case "Install from Web":
                    unset($versions[$branchKey]);
                    break;

                default:
                    $versions[$branchKey]["supported"] = false;
                    break;
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
            $apiResponse = $http->get('https://downloads.joomla.org/api/v1/releases/cms');

        } catch (ClientException $e) {
            throw new \RuntimeException("Could not fetch releases for Joomla");
        }

        $releaseList = json_decode((string) $apiResponse->getBody())->releases;
        $packages = [];

        foreach ($releaseList as $release) {
            if ($release->branch === "Install from Web" || $release->branch === "Weblinks") {
                continue;
            }

            if (in_array($release->version, self::IGNORES)) {
                continue;
            }

            $fileName = $this->getFileName($release);

            $packages[$release->version] = [
                "url" => $this->prependDownloadPath($fileName, $release),
                "filename" => $fileName
            ];
        }

        return $packages;
    }

    /**
     * Build URL for actual package download
     *
     * @param $fileName
     * @param $release
     *
     * @return string
     */
    protected function prependDownloadPath(string $fileName, \stdClass $release): string
    {
        $baseUrl = 'https://downloads.joomla.org/cms';

        switch ($release->branch) {
            case "Joomla! 4":
                $major = 4;
                break;

            case "Joomla! 3":
                $major = 3;
                break;

            case "Joomla! 2.5":
                $major = 25;
                break;

            case "Joomla! 1.5":
                $major = 15;
                break;

            case "Joomla! 1.0":
                $major = 10;
                break;

            default:
                throw new \RuntimeException("Could not find major version for Joomla " . $release->branch);
        }

        $dashedVersion = str_replace('.', '-', $release->version);

        return $baseUrl . '/joomla' . $major . '/' . $dashedVersion . '/' . $fileName;
    }

    /**
     * Extract download URL
     *
     * @param \stdClass $release
     *
     * @return string
     */
    protected function getFileName(\stdClass $release): string
    {
        $http = $this->getHttp();

        // Fetch data from
        try {
            $apiResponse = $http->get($release->relationships->signatures);
        } catch (ClientException $e) {
            throw new \RuntimeException("Could not fetch signatures for Joomla " . $release->version);
        }

        $signatures = json_decode((string) $apiResponse->getBody())->files;

        foreach ($signatures as $signature) {
            if (stripos($signature->filename, "Full") !== false) {
                return $signature->filename;
            }

            if (in_array($signature->filename, self::SPECIALNAMES)) {
                return $signature->filename;
            }
        }

        throw new \RuntimeException("Could not find package name for Joomla " . $release->version);
    }
}
