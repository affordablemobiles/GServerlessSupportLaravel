<?php

namespace A1comms\GaeSupportLaravel\Trace\Integration;

use OpenCensus\Trace\Integrations\IntegrationInterface;
use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Foundation\Http\Kernel as LaravelKernel;
use Illuminate\Http\Request as LaravelRequest;
use Illuminate\Http\Response as LaravelResponse;
use Symfony\Component\HttpFoundation\Response as BaseResponse;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Routing\Router as LaravelRouter;
use Illuminate\Routing\Pipeline as LaravelRoutePipeline;

class LaravelExtended implements IntegrationInterface
{
    public static function load()
    {
        if (!extension_loaded('opencensus')) {
            trigger_error('opencensus extension required to load Laravel integrations.', E_USER_WARNING);
            return;
        }

        // Base functionality from bootstrap/app.php and public/index.php.
        opencensus_trace_method(LaravelApplication::class, '__construct', [self::class, 'handleApplicationConstruct']);

        opencensus_trace_method(LaravelKernel::class, 'handle', [self::class, 'handleKernelRequestHandle']);

        opencensus_trace_method(LaravelRequest::class, 'capture', [self::class, 'handleRequestCapture']);

        opencensus_trace_method(LaravelResponse::class, 'send', [self::class, 'handleResponseSend']);
        opencensus_trace_method(BaseResponse::class, 'send', [self::class, 'handleResponseSend']);

        opencensus_trace_method(LaravelKernel::class, 'terminate', [self::class, 'handleKernelRequestTerminate']);

        // Trace routing, middleware & controller.
        opencensus_trace_method(LaravelRoutePipeline::class, 'through', [self::class, 'handleRoutePipeline']);

        opencensus_trace_method(LaravelRouter::class, 'dispatch', [self::class, 'handleRouterDispatch']);

        opencensus_trace_method(LaravelRoute::class, 'run', [self::class, 'handleControllerRun']);
    }

    public static function handleApplicationConstruct($scope, $basePath = null)
    {
        return [
            'name' => 'laravel/app/construct',
            'attributes' => []
        ];
    }

    public static function handleKernelRequestHandle($scope, $request)
    {
        return [
            'name' => 'laravel/kernel/handle',
            'attributes' => []
        ];
    }

    public static function handleRequestCapture()
    {
        return [
            'name' => 'laravel/request/capture',
            'attributes' => []
        ];
    }

    public static function handleResponseSend($scope)
    {
        return [
            'name' => 'laravel/response/send',
            'attributes' => []
        ];
    }

    public static function handleKernelRequestTerminate($scope, $request, $response)
    {
        return [
            'name' => 'laravel/kernel/terminate',
            'attributes' => []
        ];
    }

    public static function handleRouterDispatch($scope, $request)
    {
        return [
            'name' => 'laravel/router/dispatch',
            'attributes' => []
        ];
    }

    public static function handleRoutePipeline($scope, $pipes)
    {
        return [
            'name' => 'laravel/router/run',
            'attributes' => [
                'pipes' => var_export($pipes, true),
            ]
        ];
    }

    public static function handleControllerRun($scope)
    {
        return [
            'name' => 'laravel/controller/run',
            'attributes' => []
        ];
    }
}