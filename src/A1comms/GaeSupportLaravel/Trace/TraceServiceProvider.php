<?php

namespace A1comms\GaeSupportLaravel\Trace;

use OpenCensus\Trace\Tracer;
use Illuminate\Support\ServiceProvider;

class TraceServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ((!is_gae()) || (php_sapi_name() == 'cli')) {
            return;
        }

        // Create a span that starts from when Laravel first boots (public/index.php)
        Tracer::inSpan(['name' => 'bootstrap', 'startTime' => LARAVEL_START], function () {});
    }
}