<?php
namespace App\Http\Controllers;

use App\Http\Requests\ScanStartRequest;
use App\Jobs\VersionScanJob;
use App\VersionScan;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class ApiController extends Controller
{
    public function versionReport(ScanStartRequest $request)
    {
        if ($request->json('callbackurls')) {
            VersionScanJob::dispatch($request->all());

            return 'OK';
        }

        return json_encode((new VersionScan($request))->report());
    }

}
