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
        // ---
        // TODO: Set parentSpanId to the rootSpan->spanId() from OpenSensus,
        //       to help it merge properly in the tree view.
        //       Need to wait for rootSpan visibility to be changed to public.
        //       https://github.com/census-instrumentation/opencensus-php/issues/199
        Tracer::inSpan(['name' => 'laravel/bootstrap', 'startTime' => LARAVEL_START], function () {});

        foreach (config('gaesupport.trace_providers', []) as $p) {
            $p::load();
        }
    }
}