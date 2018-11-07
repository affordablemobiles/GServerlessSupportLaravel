<?php

use OpenCensus\Trace\Tracer;
use OpenCensus\Trace\Exporter\StackdriverExporter;

require __DIR__ . '/helpers.php';

if (is_gae() && (php_sapi_name() != 'cli')){
    // Properly set REMOTE_ADDR from a trustworthy source (hopefully).
    if (!empty($_SERVER['HTTP_X_APPENGINE_USER_IP'])) {
        $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_APPENGINE_USER_IP'];
    }

    if (is_gae_flex()){
        Tracer::start(new StackdriverExporter(['async' => true]));
    } else {
        // TODO: Async on Standard Environment too!
        Tracer::start(new StackdriverExporter());
    }

    $loaderInterface = 'App\\Trace\\LowLevelLoader';
    if (!class_exists($loaderInterface))
    {
        // TODO: Different default arrays for Laravel vs Lumen?
        $loaderInterface = A1comms\GaeSupportLaravel\Trace\LowLevelLoader::class;
    }
    $traceProviders = $loaderInterface::getList();

    foreach ($traceProviders as $p) {
        $p::load();
    }

    if (in_array('HTTP_X_APPENGINE_HTTPS', $_SERVER)) {
        // Turn HTTPS on for Laravel
        $_SERVER['HTTPS'] = $_SERVER['HTTP_X_APPENGINE_HTTPS'];
    }
}