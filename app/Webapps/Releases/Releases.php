<?php
declare(strict_types=1);

namespace App\Webapps\Releases;

use GuzzleHttp\Client;

abstract class Releases
{
    abstract public function getLatest();

    abstract public function getPackages();

    /**
     * Return http client
     *
     * @return Client
     */
    public function getHttp()
    {
        return \App::make('guzzle');
    }
}
