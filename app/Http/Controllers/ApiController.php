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

    public static function notifyCallbacks(array $callbackurls, $report)
    {
        foreach ($callbackurls as $url) {
            try {
                $client = new Client();
                $client->post($url, [
                    'http_errors' => false,
                    'timeout'     => 60,
                    'json'        => $report,
                ]);
            } catch (\Exception $e) {
                Log::warning('Could not send the report to the following callback url: '.$url);
            }
        }
    }
}