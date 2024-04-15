<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Trace;

use Illuminate\Support\ServiceProvider;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;

class TraceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ((!is_g_serverless()) || (\PHP_SAPI === 'cli')) {
            return;
        }

        $instrumentation = new CachedInstrumentation('g-serverless-support-laravel.opentelemetry.provider');

        foreach (config('gserverlesssupport.trace_instrumentation', []) as $inst) {
            $inst::register($instrumentation);
        }
    }
}
