<?php
namespace App;

use App\Http\Requests\ScanStartRequest;
use GuzzleHttp\Client;

class VersionScan
{
    protected $response = null;

    public function __construct(ScanStartRequest $request, Client $client = null)
    {

    }

    public function report()
    {
        return [];
    }
}
