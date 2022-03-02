<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Trace;

use Illuminate\Support\ServiceProvider;
use OpenCensus\Trace\Tracer;

class TraceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ((!is_gae()) || (PHP_SAPI === 'cli')) {
            return;
        }

        // Create a span that starts from when Laravel first boots (public/index.php)
        // ---
        // TODO: Set parentSpanId to the rootSpan->spanId() from OpenCensus,
        //       to help it merge properly in the tree view.
        //       Need to wait for rootSpan visibility to be changed to public.
        //       https://github.com/census-instrumentation/opencensus-php/issues/199
        if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            Tracer::inSpan(['name' => 'laravel/bootstrap', 'startTime' => $_SERVER['REQUEST_TIME_FLOAT']], function (): void {
            });
        } elseif (\defined('LARAVEL_START')) {
            Tracer::inSpan(['name' => 'laravel/bootstrap', 'startTime' => LARAVEL_START], function (): void {
            });
        }

        foreach (config('gaesupport.trace_providers', []) as $p) {
            $p::load();
        }
    }
}
