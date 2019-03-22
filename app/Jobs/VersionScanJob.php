<?php

namespace App\Jobs;

use App\VersionScan;
use App\Http\Requests\ScanStartRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class VersionScanJob implements ShouldQueue
{
    protected $request;

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($request)
    {
        $this->request = new ScanStartRequest($request);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Set up scan from request
        $scan = new VersionScan(
            $this->request->get('url'),
            (10 - $this->request->get('dangerLevel', 0)) * 10,
            $this->request->get('callbackurls', []),
            $this->request->get('userAgent', 'SIWECOS Version Scanner')
        );

        // Execute scan
        $scan->scan();
    }
}
