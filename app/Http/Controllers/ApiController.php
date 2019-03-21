<?php
namespace App\Http\Controllers;

use App\Http\Requests\ScanStartRequest;
use App\Jobs\VersionScanJob;
use App\VersionScan;

class ApiController extends Controller
{
    public function versionReport(ScanStartRequest $request)
    {
        if ($request->json('callbackurls')) {
            VersionScanJob::dispatch($request->all());

            return 'OK';
        }

        $scan = new VersionScan(
            $request->get('url'),
            10 - $request->get('dangerLevel', 0) * 100,
            $request->get('callbackurls', []),
            $request->get('userAgent', 'SIWECOS Version Scanner')
        );

        return response(
            $scan->scan()
        );
    }

}
