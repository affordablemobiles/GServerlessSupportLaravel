<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Trace\Integration\LowLevel;

use A1comms\GaeSupportLaravel\View\Engines\CompilerEngine;
use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Foundation\Http\Kernel as LaravelKernel;
use Illuminate\Http\Request as LaravelRequest;
use Illuminate\Http\Response as LaravelResponse;
use Illuminate\Pipeline\Pipeline as LaravelPipeline;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Routing\Router as LaravelRouter;
use Laravel\Lumen\Application as LumenApplication;
use Laravel\Lumen\Routing\Pipeline as LumenPipeline;
use OpenCensus\Trace\Integrations\IntegrationInterface;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class LaravelExtended implements IntegrationInterface
{
    public static function load(): void
    {
        if (!\extension_loaded('opencensus')) {
            trigger_error('opencensus extension required to load Laravel integrations.', E_USER_WARNING);

            return;
        }

        // ---
        // Base functionality from bootstrap/app.php and public/index.php.
        // ---
        // We feel this helps to get a feel of the control flow and weight of the framework,
        // or simply rule out a problem there, before you jump in to analysing your own work.
        opencensus_trace_method(LaravelApplication::class, '__construct', [self::class, 'handleApplicationConstruct']);

        opencensus_trace_method(LaravelKernel::class, 'handle', [self::class, 'handleKernelRequestHandle']);

        opencensus_trace_method(LaravelRequest::class, 'capture', [self::class, 'handleRequestCapture']);

        opencensus_trace_method(LaravelResponse::class, 'send', [self::class, 'handleResponseSend']);
        // TODO: Eventually we want to be able to remove this,
        //       the "send" method in "LaravelResponse" is inherited from "BaseResponse"
        //       but unfortunately the OpenCensus extension doesn't trigger for inherited methods.
        //       https://github.com/census-instrumentation/opencensus-php/issues/201
        opencensus_trace_method(BaseResponse::class, 'send', [self::class, 'handleResponseSend']);

        opencensus_trace_method(LaravelKernel::class, 'terminate', [self::class, 'handleKernelRequestTerminate']);

        // ---
        // Trace routing, middleware & controller.
        // ---

        // Use a trace on the pipeline through method to intercept the array of middleware in use,
        // by receiving it in the scope we are passed.
        opencensus_trace_method(LaravelPipeline::class, 'through', [self::class, 'handlePipeline']);

        // Trace when routing begins,
        // making it possible to infer how long the routing took by looking at when
        // the middleware or the controller execution begin.
        opencensus_trace_method(LaravelRouter::class, 'dispatch', [self::class, 'handleRouterDispatch']);

        // Trace the controller run, where we'd expect the bit of exeuction we care about to happen.
        opencensus_trace_method(LaravelRoute::class, 'run', [self::class, 'handleControllerRun']);

        // ---
        // Alternative View Compiler for Pre-Compiled Views
        // ---
        opencensus_trace_method(CompilerEngine::class, 'get', [self::class, 'handleView']);

        // ---
        // Lumen compatiility...
        // ---
        opencensus_trace_method(LumenApplication::class, '__construct', [self::class, 'handleApplicationConstruct']);

        opencensus_trace_method(LumenApplication::class, 'handle', [self::class, 'handleKernelRequestHandle']);
        opencensus_trace_method(LumenApplication::class, 'run', [self::class, 'handleKernelRequestHandle']);

        opencensus_trace_method(LumenApplication::class, 'dispatch', [self::class, 'handleRouterDispatch']);

        opencensus_trace_method(LumenApplication::class, 'callActionOnArrayBasedRoute', [self::class, 'handleControllerRun']);

        opencensus_trace_method(LumenPipeline::class, 'through', [self::class, 'handlePipeline']);
    }

    public static function handleApplicationConstruct($scope, $basePath = null)
    {
        return [
            'name'       => 'laravel/app/construct',
            'attributes' => [],
        ];
    }

    public static function handleKernelRequestHandle($scope, $request)
    {
        return [
            'name'       => 'laravel/kernel/handle',
            'attributes' => [],
        ];
    }

    public static function handleRequestCapture()
    {
        return [
            'name'       => 'laravel/request/capture',
            'attributes' => [],
        ];
    }

    public static function handleResponseSend($scope)
    {
        return [
            'name'       => 'laravel/response/send',
            'attributes' => [],
        ];
    }

    public static function handleKernelRequestTerminate($scope, $request, $response)
    {
        return [
            'name'       => 'laravel/kernel/terminate',
            'attributes' => [],
        ];
    }

    public static function handleRouterDispatch($scope, $request)
    {
        return [
            'name'       => 'laravel/router/dispatch',
            'attributes' => [],
        ];
    }

    public static function handlePipeline($scope, $pipes)
    {
        // Grab the middleware array in this event,
        // then force a trace on all children.
        $tracedMiddleware = [];
        foreach ($pipes as $p) {
            if (\is_callable($p)) {
                // Can't handle closures yet.
            } elseif (!\is_object($p)) {
                [$name, $parameters] = self::parsePipeString($p);
                $tracedMiddleware[]  = $name;
                // ---
                // Disable this as it's causing segfaults, see:
                // https://github.com/census-instrumentation/opencensus-php/issues/200
                // ---
                // opencensus_trace_method($name, 'handle', [self::class, 'handleMiddlewareRun']);
            }
            // Can't handle already objects yet.
        }

        return [
            'name'       => 'laravel/pipeline/register',
            'attributes' => $tracedMiddleware,
        ];
    }

    public static function handleMiddlewareRun($scope)
    {
        return [
            'name'       => 'laravel/middleware/run',
            'attributes' => [
                'name' => $scope::class,
            ],
        ];
    }

    public static function handleControllerRun($scope)
    {
        return [
            'name'       => 'laravel/controller/run',
            'attributes' => [],
        ];
    }

    public static function handleView($scope, $path, $data)
    {
        return [
            'name'       => 'laravel/view',
            'attributes' => [
                'path'         => $path,
                'pre-compiled' => true,
            ],
        ];
    }

    /*
    | Taken from Illuminate\Pipeline\Pipeline
    | as the visibility was set to protected
    */
    public static function parsePipeString($pipe)
    {
        [$name, $parameters] = array_pad(explode(':', $pipe, 2), 2, []);

        if (\is_string($parameters)) {
            $parameters = explode(',', $parameters);
        }

        return [$name, $parameters];
    }
}
