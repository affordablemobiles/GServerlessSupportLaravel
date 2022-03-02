<?php

declare(strict_types=1);

use A1comms\GaeSupportLaravel\Integration\ErrorReporting\Report as ErrorBootstrap;
use Google\Cloud\Storage\StorageClient;
use OpenCensus\Trace\Exporter\StackdriverExporter;
use OpenCensus\Trace\Tracer;

require __DIR__.'/helpers.php';

// Load in the Laravel / Lumen support helpers, for the "env()" function,
// as we may be loading before them, resulting in undefined function errors
// in the Trace initialisation.
$laravelHelpers = __DIR__.'/../../../laravel/framework/src/Illuminate/Support/helpers.php';
$lumenHelpers = __DIR__.'/../../../illuminate/support/helpers.php';
if (is_file($laravelHelpers)) {
    require $laravelHelpers;
} elseif (is_file($lumenHelpers)) {
    require $lumenHelpers;
}

if (is_gae() && (PHP_SAPI !== 'cli')) {
    // Set up exception logging properly...
    ErrorBootstrap::init();

    // Properly set REMOTE_ADDR from a trustworthy source (hopefully).
    if (!empty($_SERVER['HTTP_X_APPENGINE_USER_IP'])) {
        $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_APPENGINE_USER_IP'];
    } elseif (is_cloud_run()) {
        $forwards = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $_SERVER['REMOTE_ADDR'] = trim(array_pop($forwards));
    }
    if (!empty($_SERVER['HTTP_X_APPENGINE_HTTPS'])) {
        // Turn HTTPS on for Laravel
        $_SERVER['HTTPS'] = $_SERVER['HTTP_X_APPENGINE_HTTPS'];
    }

    $storage = new StorageClient();
    $storage->registerStreamWrapper();

    if (!defined('GAE_TRACE_STOP')) {
        $options = [
            'sampler' => (
                new A1comms\GaeSupportLaravel\Trace\Sampler\HttpHeaderSampler()
            ),
            'propagator' => (
                new OpenCensus\Trace\Propagator\HttpHeaderPropagator(
                    (new A1comms\GaeSupportLaravel\Trace\Propagator\CloudTraceFormatter())
                )
            ),
        ];

        if (is_gae_flex()) {
            Tracer::start(new StackdriverExporter(['async' => true]), $options);
        } else {
            // TODO: Async on Standard Environment too!
            Tracer::start(new StackdriverExporter(), $options);
        }
    }

    $loaderInterface = 'App\\Trace\\LowLevelLoader';
    if (!class_exists($loaderInterface)) {
        // TODO: Different default arrays for Laravel vs Lumen?
        $loaderInterface = A1comms\GaeSupportLaravel\Trace\LowLevelLoader::class;
    }
    $traceProviders = $loaderInterface::getList();

    foreach ($traceProviders as $p) {
        $p::load();
    }
}
