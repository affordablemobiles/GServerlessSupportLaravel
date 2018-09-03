<?php

use OpenCensus\Trace\Tracer;
use OpenCensus\Trace\Exporter\StackdriverExporter;

require __DIR__ . '/helpers.php';

if (is_gae() && (php_sapi_name() != 'cli')){
    if (is_gae_flex()){
        Tracer::start(new StackdriverExporter(['async' => true]));
    } else {
        // TODO: Async on Standard Environment too!
        Tracer::start(new StackdriverExporter());
    }

    // TODO: Different arrays for Laravel vs Lumen?
    $traceProviders = [
        // OpenSensus provides a basic Laravel trace adapter,
        // which covered Eloquent and view compilation.
        OpenCensus\Trace\Integrations\Laravel::class,
        // Also load our own extended Laravel trace set.
        A1comms\GaeSupportLaravel\Trace\Integration\LaravelExtended::class,
        // Trace our other basic functions...
        OpenCensus\Trace\Integrations\Mysql::class,
        OpenCensus\Trace\Integrations\PDO::class,
        OpenCensus\Trace\Integrations\Memcached::class,
    ];

    foreach ($traceProviders as $p) {
        $p::load();
    }
}