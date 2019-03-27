<?php

namespace App\Jobs;

use App\VersionScan;
use App\Http\Requests\ScanStartRequest;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

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
        Log::info('Starting Scan Job for ' . $this->request->get('url'));
        Log::info('Queue jobs remaining ' . Queue::size($this->queue));

        // Set up scan from request
        $scan = new VersionScan(
            $this->request->get('url'),
            (int) (10 - $this->request->get('dangerLevel', 0)) * 20 + 10,
            $this->request->get('callbackurls', []),
            $this->request->get('userAgent', 'SIWECOS Version Scanner')
        );

        // Execute scan
        $scan->scan();
    }

    /**
     * The job failed to process.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function failed(\Exception $exception)
    {
        foreach ($this->request->get('callbackurls', []) as $url) {
            Log::info(
                'Making job-failed callback to ' . $url . ' with content ' . json_encode($exception->getMessage())
            );

            try {
                $client = new Client;
                $client->post(
                    $url,
                    [
                        'http_errors' => false,
                        'timeout' => 60,
                        'json' => [
                            'name'         => 'CMSVersion',
                            'version'      => file_get_contents(base_path('VERSION')),
                            'hasError'     => true,
                            'errorMessage' => $exception->getMessage(),
                            'score'        => 0
                        ],
                    ]
                );
            } catch (\Exception $e) {
                Log::warning('Could not send the failed report to the following callback url: ' . $url);
            }
        }
    }
}
