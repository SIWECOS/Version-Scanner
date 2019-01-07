<?php
declare(strict_types=1);

namespace App\Webapps;

class CandidateBuilder
{
    protected $signatures = [];

    protected $versions = [];

    protected $versionCount = 0;

    public function __construct(array $signatures, array $versions)
    {
        if (!count($signatures)) {
            throw new \InvalidArgumentException("Empty signature list provided");
        }

        if (!count($versions)) {
            throw new \InvalidArgumentException("Invalid version array");
        }

        $this->signatures = $signatures;
        $this->versions = $versions;
        $this->versionCount = count($versions);
    }

    public function getVersionproofCandidates()
    {
        $proofs = [];

        // Update version array
        foreach ($this->versions as $version) {
            $proofs[$version] = [];

            foreach ($this->signatures as $file => $hashes) {
                foreach ($hashes as $hash => $hashVersions) {
                    if (count($hashVersions) === 1 && $hashVersions[0] === $version) {
                        $proofs[$version][$file] = $hash;
                        continue 2;
                    }
                }
            }

            if (!count($proofs[$version])) {
                $proofs[$version] = false;
            }
        }

        return $proofs;
    }

    public function getIdentifierCandidates(int $results = 100)
    {
        $candidates = [];

        foreach ($this->signatures as $file => $hashes) {
            $versionNoScore = (count($hashes, COUNT_RECURSIVE) - count($hashes)) / $this->versionCount;
            $changeNoScore = count($hashes) / $this->versionCount;

            $candidates[$file] = [
                "score" => $versionNoScore + $changeNoScore,
                "data" => $hashes
            ];
        }

        // Sort by score
        uasort($candidates, function ($a, $b) {
            if ($a["score"] === $b["score"]) {
                return 0;
            }

            return ($a["score"] < $b["score"]) ? 1 : -1;
        });

        // Shorten candidate list
        return array_slice($candidates, 0, $results, true);
    }
}
