<?php

declare(strict_types=1);

use AffordableMobiles\GServerlessSupportLaravel\Integration\ErrorReporting\Report as ErrorBootstrap;
use Google\Cloud\Storage\StorageClient;
use OpenCensus\Trace\Exporter\StackdriverExporter;
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

if (is_g_serverless() && (PHP_SAPI !== 'cli')) {
    // Set up exception logging properly...
    ErrorBootstrap::init();

    // Properly set REMOTE_ADDR from a trustworthy source (hopefully).
    if (!empty($_SERVER['HTTP_X_APPENGINE_USER_IP'])) {
        $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_APPENGINE_USER_IP'];
    } elseif (!empty($_SERVER['HTTP_CF_CONNECTING_IP']) {
        $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (!empty($_SERVER['HTTP_X_REAL_IP']) {
        $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_REAL_IP'];
    } else {
        $forwards               = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $_SERVER['REMOTE_ADDR'] = trim(array_pop($forwards));
    }

    if (!empty($_SERVER['HTTP_X_APPENGINE_HTTPS'])) {
        // Turn HTTPS on for Laravel
        $_SERVER['HTTPS'] = $_SERVER['HTTP_X_APPENGINE_HTTPS'];
    }

    $storage = new StorageClient();
    $storage->registerStreamWrapper();

    if (g_serverless_should_trace()) {
        OpenTelemetry\API\Globals::registerInitializer(function (Configurator $configurator) {
            $propagator = OpenTelemetry\Extension\Propagator\CloudTrace\CloudTracePropagator::getInstance();

            $spanProcessor = new OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor(
                (new AffordableMobiles\OpenTelemetry\CloudTrace\SpanExporterFactory())->create(),
            );

            $tracerProvider = (new OpenTelemetry\SDK\Trace\TracerProviderBuilder())
                ->addSpanProcessor($spanProcessor)
                ->setSampler(
                    new OpenTelemetry\SDK\Trace\Sampler\ParentBased(
                        new OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler(),
                    ),
                )
                ->build();
        
            ShutdownHandler::register([$tracerProvider, 'shutdown']);
        
            return $configurator
                ->withTracerProvider($tracerProvider)
                ->withPropagator($propagator);
        });

        $loaderInterface = 'App\\Trace\\LowLevelLoader';
        if (!class_exists($loaderInterface)) {
            // TODO: Different default arrays for Laravel vs Lumen?
            $loaderInterface = AffordableMobiles\GServerlessSupportLaravel\Trace\LowLevelLoader::class;
        }
        $loaderInterface::load();
    }
}
