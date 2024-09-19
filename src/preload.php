<?php

declare(strict_types=1);

use A1comms\GaeSupportLaravel\Integration\ErrorReporting\Report as ErrorBootstrap;
use A1comms\GaeSupportLaravel\Trace\LowLevelLoader;
use A1comms\GaeSupportLaravel\Trace\Propagator\CloudTraceFormatter;
use A1comms\GaeSupportLaravel\Trace\Sampler\HttpHeaderSampler;
use Google\Cloud\Storage\StorageClient;
use OpenCensus\Trace\Exporter\StackdriverExporter;
use OpenCensus\Trace\Propagator\HttpHeaderPropagator;
use OpenCensus\Trace\Tracer;

require __DIR__.'/helpers.php';

// Load in the Laravel / Lumen support helpers, for the "env()" function,
// as we may be loading before them, resulting in undefined function errors
// in the Trace initialisation.
$helpers = [
    __DIR__.'/../../../laravel/framework/src/Illuminate/Support/helpers.php',
    __DIR__.'/../../../illuminate/support/helpers.php',
    __DIR__.'/../../../laravel/framework/src/Illuminate/Collections/helpers.php',
    __DIR__.'/../../../illuminate/collections/helpers.php',
];
foreach ($helpers as $helper) {
    if (is_file($helper)) {
        require $helper;
    }
}

if (is_gae() && (PHP_SAPI !== 'cli')) {
    // Set up exception logging properly...
    ErrorBootstrap::init();

    // Properly set REMOTE_ADDR from a trustworthy source (hopefully).
    if (!empty($_SERVER['HTTP_X_APPENGINE_USER_IP'])) {
        $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_APPENGINE_USER_IP'];
    } elseif (is_cloud_run()) {
        $forwards               = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
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
                new HttpHeaderSampler()
            ),
            'propagator' => (
                new HttpHeaderPropagator(
                    new CloudTraceFormatter()
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

    $loaderInterface = 'App\Trace\LowLevelLoader';
    if (!class_exists($loaderInterface)) {
        // TODO: Different default arrays for Laravel vs Lumen?
        $loaderInterface = LowLevelLoader::class;
    }
    $traceProviders = $loaderInterface::getList();

    foreach ($traceProviders as $p) {
        $p::load();
    }
}
