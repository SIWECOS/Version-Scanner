<?php
declare(strict_types=1);

namespace App\Webapps;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class SignatureBuilder
{
    const FILETYPES = ["js", "css", "png", "jpg", "gif", "sql", "txt", "html", "md", "sh"];

    protected $config = [
        "ignoredFolders" => [],
        "ignoredFilenames" => [],
        "webroot" => "/"
    ];

    protected $path;

    public function __construct(string $path, array $config)
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("Invalid webapp base path");
        }

        $this->path = $path;

        $this->config = array_merge($this->config, $config);
    }

    public function buildSignatures()
    {
        $signatures = [];

        $finder = Finder::create()->files()
            ->ignoreVCS(true)
            ->ignoreDotFiles(true)
            ->ignoreUnreadableDirs()
            ->in($this->path . $this->config["webroot"]);

        // Directory exclusion
        if (count($this->config['ignoredFolders'])) {
            $finder->notPath($this->config['ignoredFolders']);
        }

        // Filename exclusion
        if (count($this->config['ignoredFilenames'])) {
            foreach ($this->config['ignoredFilenames'] as $ignoredFilename) {
                $finder->notName($ignoredFilename);
            }
        }

        // Filetype filter
        foreach (self::FILETYPES as $type) {
            $finder->name('*.' . $type);
        }

        /** @var SplFileInfo $item */
        foreach ($finder as $item) {
            $signatures[$item->getRelativePath() . '/' . $item->getFilename()] = md5($item->getContents());
        }

        return $signatures;
    }
}
