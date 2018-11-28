<?php

namespace A1comms\GaeSupportLaravel\Trace\Integration\LowLevel;

use OpenCensus\Trace\Integrations\IntegrationInterface;
use Laravel\Lumen\Application as LumenApplication;
use Illuminate\Http\Request as LumenRequest;
use Illuminate\Http\Response as LumenResponse;
use Symfony\Component\HttpFoundation\Response as BaseResponse;
use FastRoute\Dispatcher\RegexBasedAbstract as LumenRouter;
use Illuminate\Pipeline\Pipeline as LaravelPipeline;
use A1comms\GaeSupportLaravel\View\Engines\CompilerEngine;

class Lumen implements IntegrationInterface
{
    public static function load()
    {
        if (!extension_loaded('opencensus')) {
            trigger_error('opencensus extension required to load Lumen integrations.', E_USER_WARNING);
            return;
        }

        // ---
        // Base functionality from bootstrap/app.php and public/index.php.
        // ---
        // We feel this helps to get a feel of the control flow and weight of the framework,
        // or simply rule out a problem there, before you jump in to analysing your own work.
        opencensus_trace_method(LumenApplication::class, '__construct', [self::class, 'handleApplicationConstruct']);

        opencensus_trace_method(LumenApplication::class, 'run', [self::class, 'handleApplicationRequestRun']);

        opencensus_trace_method(LumenRequest::class, 'capture', [self::class, 'handleRequestCapture']);

        opencensus_trace_method(LumenResponse::class, 'send', [self::class, 'handleResponseSend']);
        // TODO: Eventually we want to be able to remove this,
        //       the "send" method in "LaravelResponse" is inherited from "BaseResponse"
        //       but unfortunately the OpenCensus extension doesn't trigger for inherited methods.
        //       https://github.com/census-instrumentation/opencensus-php/issues/201
        opencensus_trace_method(BaseResponse::class, 'send', [self::class, 'handleResponseSend']);

        opencensus_trace_method(LumenApplication::class, 'callTerminableMiddleware', [self::class, 'handleApplicationRequestTerminate']);

        // ---
        // Trace routing, middleware & controller.
        // ---

        // Use a trace on the pipeline through method to intercept the array of middleware in use,
        // by receiving it in the scope we are passed.
        opencensus_trace_method(LaravelPipeline::class, 'through', [self::class, 'handlePipeline']);

        // Trace when routing begins,
        // making it possible to infer how long the routing took by looking at when
        // the middleware or the controller execution begin.
        opencensus_trace_method(LumenApplication::class, 'createDispatcher', [self::class, 'handleCreateDispatch']);
        opencensus_trace_method(LumenRouter::class, 'dispatch', [self::class, 'handleRouterDispatch']);


        // Trace the controller run, where we'd expect the bit of exeuction we care about to happen.
        opencensus_trace_method(LumenApplication::class, 'handleFoundRoute', [self::class, 'handleControllerRun']);

        // ---
        // Alternative View Compiler for Pre-Compiled Views
        // ---
        opencensus_trace_method(CompilerEngine::class, 'get', [self::class, 'handleView']);
    }

    public static function handleApplicationConstruct($scope, $basePath = null)
    {
        return [
            'name' => 'lumen/app/construct',
            'attributes' => []
        ];
    }

    public static function handleApplicationRequestRun($scope, $request)
    {
        return [
            'name' => 'lumen/app/run',
            'attributes' => []
        ];
    }

    public static function handleRequestCapture()
    {
        return [
            'name' => 'lumen/request/capture',
            'attributes' => []
        ];
    }

    public static function handleResponseSend($scope)
    {
        return [
            'name' => 'lumen/response/send',
            'attributes' => []
        ];
    }

    public static function handleApplicationRequestTerminate($scope, $response)
    {
        return [
            'name' => 'lumen/app/terminate',
            'attributes' => []
        ];
    }

    public static function handleCreateDispatch($scope, $request)
    {
        return [
            'name' => 'lumen/router/setup',
            'attributes' => []
        ];
    }

    public static function handleRouterDispatch($scope, $request)
    {
        return [
            'name' => 'lumen/router/dispatch',
            'attributes' => []
        ];
    }

    public static function handlePipeline($scope, $pipes)
    {
        // Grab the middleware array in this event,
        // then force a trace on all children.
        $tracedMiddleware = [];
        foreach ($pipes as $p) {
            if (is_callable($p)) {
                // Can't handle closures yet.
            } elseif (! is_object($p)) {
                list($name, $parameters) = self::parsePipeString($p);
                $tracedMiddleware[] = $name;
                // ---
                // Disable this as it's causing segfaults, see:
                // https://github.com/census-instrumentation/opencensus-php/issues/200
                // ---
                //opencensus_trace_method($name, 'handle', [self::class, 'handleMiddlewareRun']);
            } else {
                // Can't handle already objects yet.
            }
        }

        return [
            'name' => 'lumen/pipeline/register',
            'attributes' => $tracedMiddleware
        ];
    }

    public static function handleMiddlewareRun($scope)
    {
        return [
            'name' => 'lumen/middleware/run',
            'attributes' => [
                'name' => get_class($scope),
            ]
        ];
    }

    public static function handleControllerRun($scope)
    {
        return [
            'name' => 'lumen/controller/run',
            'attributes' => []
        ];
    }

    public static function handleView($scope, $path, $data)
    {
        return [
            'name' => 'lumen/view',
            'attributes' => [
                'path' => $path,
                'pre-compiled' => true,
            ]
        ];
    }

    /*
    | Taken from Illuminate\Pipeline\Pipeline
    | as the visibility was set to protected
    */
    public static function parsePipeString($pipe)
    {
        list($name, $parameters) = array_pad(explode(':', $pipe, 2), 2, []);

        if (is_string($parameters)) {
            $parameters = explode(',', $parameters);
        }

        return [$name, $parameters];
    }
}