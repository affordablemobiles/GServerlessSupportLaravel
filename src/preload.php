<?php

use OpenCensus\Trace\Tracer;
use OpenCensus\Trace\Exporter\StackdriverExporter;
use A1comms\GaeSupportLaravel\Integration\ErrorReporting as ErrorBootstrap;

require __DIR__ . '/helpers.php';

if ( is_gae_std_legacy() ) {
    define('GAE_LEGACY', true);
} else {
    define('GAE_LEGACY', false);
}

if ( GAE_LEGACY ) {
    $_SERVER['GOOGLE_CLOUD_PROJECT'] = explode("~", $_SERVER['APPLICATION_ID'])[1];
    $_SERVER['GAE_ENV'] = "standard";
    $_SERVER['GAE_VERSION'] = $_SERVER['CURRENT_VERSION_ID'];
    $_SERVER['GAE_SERVICE'] = $_SERVER['CURRENT_MODULE_ID'];
    $_SERVER['GAE_INSTANCE'] = $_SERVER['INSTANCE_ID'];
} else if (is_gae() && (php_sapi_name() != 'cli')) {
    // Set up exception logging properly...
    ErrorBootstrap::init();

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