<?php

namespace A1comms\GaeSupportLaravel\Trace\Integration;

use OpenCensus\Trace\Integrations\IntegrationInterface;
use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Foundation\Http\Kernel as LaravelKernel;
use Illuminate\Http\Request as LaravelRequest;
use Illuminate\Http\Response as LaravelResponse;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class LaravelExtended implements IntegrationInterface
{
    public static function load()
    {
        if (!extension_loaded('opencensus')) {
            trigger_error('opencensus extension required to load Laravel integrations.', E_USER_WARNING);
            return;
        }

        opencensus_trace_method(LaravelApplication::class, '__construct', [self::class, 'handleApplicationConstruct']);

        opencensus_trace_method(LaravelKernel::class, 'handle', [self::class, 'handleKernelRequestHandle']);

        opencensus_trace_method(LaravelRequest::class, 'capture', [self::class, 'handleRequestCapture']);

        opencensus_trace_method(LaravelResponse::class, 'send', [self::class, 'handleResponseSend']);
        opencensus_trace_method(BaseResponse::class, 'send', [self::class, 'handleResponseSend']);

        opencensus_trace_method(LaravelKernel::class, 'terminate', [self::class, 'handleKernelRequestTerminate']);
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
}