<?php

declare(strict_types=1);

use AffordableMobiles\GServerlessSupportLaravel\Integration\ErrorReporting\Report as ErrorBootstrap;
use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\Guzzle\GuzzleInstrumentation;
use AffordableMobiles\GServerlessSupportLaravel\Trace\Propagator\CloudTracePropagator;
use AffordableMobiles\OpenTelemetry\CloudTrace\SpanExporterFactory;
use App\Trace\InstrumentationLoader;
use Google\Cloud\Storage\StorageClient;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOffSampler;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProviderBuilder;

require __DIR__.'/helpers.php';

// Load in the Laravel support helpers, for the "env()" function,
// as we may be loading before them, resulting in undefined function errors
// in the Trace initialisation.
$helpers = [
    __DIR__.'/../../../laravel/framework/src/Illuminate/Support/helpers.php',
    __DIR__.'/../../../laravel/framework/src/Illuminate/Collections/helpers.php',
];
foreach ($helpers as $helper) {
    if (is_file($helper)) {
        require $helper;
    }
}

if (is_g_serverless() && (PHP_SAPI !== 'cli')) {
    try {
        putenv('GOOGLE_CLOUD_BATCH_DAEMON_FAILURE_DIR=false');
        putenv('OTEL_PHP_DETECTORS=none');

        require __DIR__.'/AffordableMobiles/GServerlessSupportLaravel/Trace/Propagator/_register.php';

        Context::getCurrent()->withContextValue(
            Span::wrap(
                g_serverless_trace_context(),
            ),
        )->activate();

        $instrumentation = new CachedInstrumentation('g-serverless-support-laravel.opentelemetry.low-level');

        $loaderInterface = InstrumentationLoader::class;
        if (!class_exists($loaderInterface)) {
            $loaderInterface = AffordableMobiles\GServerlessSupportLaravel\Trace\InstrumentationLoader::class;
        }
        $preregisterList = [
            GuzzleInstrumentation::class,
        ];
        $instrumentationList = $loaderInterface::getInstrumentation();

        foreach ($preregisterList as $pre) {
            if (in_array($pre, $instrumentationList, true)) {
                $instrumentationList = array_filter($instrumentationList, static fn ($var) => $var !== $pre);
                $pre::register($instrumentation);
            }
        }

        $propagator = CloudTracePropagator::getInstance();

        $spanProcessor = new SimpleSpanProcessor(
            (new SpanExporterFactory())->create(),
        );

        $sampler = new ParentBased(
            new AlwaysOnSampler(),
        );
        if (!empty($_SERVER['G_SERVERLESS_TRACE_STOP'])) {
            $sampler = new AlwaysOffSampler();
        } elseif (is_g_serverless_development()) {
            $sampler = new AlwaysOnSampler();
        }

        $tracerProvider = (new TracerProviderBuilder())
            ->addSpanProcessor($spanProcessor)
            ->setSampler($sampler)
            ->build()
        ;

        Sdk::builder()
            ->setTracerProvider($tracerProvider)
            ->setPropagator($propagator)
            ->setAutoShutdown(true)
            ->buildAndRegisterGlobal()
        ;

        foreach ($instrumentationList as $inst) {
            $inst::register($instrumentation);
        }
    } catch (Throwable $ex) {
        ErrorBootstrap::init();
        ErrorBootstrap::exceptionHandler($ex, 200);
    }

    // Set up exception logging properly...
    ErrorBootstrap::init();

    // Properly set REMOTE_ADDR from a trustworthy source (hopefully).
    $sourceIPHeader = 'HTTP_'.strtoupper(
        str_replace(
            '-',
            '_',
            env('SOURCE_IP_HEADER', 'X-AppEngine-User-IP'),
        ),
    );

    if (!empty($_SERVER[$sourceIPHeader])) {
        $_SERVER['REMOTE_ADDR'] = $_SERVER[$sourceIPHeader];
    } elseif (!empty($_SERVER['HTTP_X_APPENGINE_USER_IP'])) {
        $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_APPENGINE_USER_IP'];
    } else {
        $forwards               = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $_SERVER['REMOTE_ADDR'] = trim(array_shift($forwards));
    }
    g_serverless_basic_log('audit', 'INFO', 'Correcting Source IP Address (REMOTE_ADDR) to '.$_SERVER['REMOTE_ADDR'], ['ip_address' => $_SERVER['REMOTE_ADDR']]);

    if (!empty($_SERVER['HTTP_X_APPENGINE_HTTPS'])) {
        // Turn HTTPS on for Laravel
        $_SERVER['HTTPS'] = $_SERVER['HTTP_X_APPENGINE_HTTPS'];
    }

    $storage = new StorageClient([
        'projectId' => g_project(),
    ]);
    $storage->registerStreamWrapper();
}
