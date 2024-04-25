<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\Laravel;

use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\SimpleSpan;
use Illuminate\Foundation\Bootstrap\BootProviders;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Foundation\Bootstrap\RegisterFacades;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Foundation\Configuration\ApplicationBuilder;
use Illuminate\Http\Request;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;

use function OpenTelemetry\Instrumentation\hook;

class LaravelExtendedInstrumentation
{
    public const NAME = 'laravel-extended';

    public static function register(CachedInstrumentation $instrumentation): void
    {
        hook(
            ApplicationBuilder::class,
            '__construct',
            pre: static function (ApplicationBuilder $builder, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                SimpleSpan::pre($instrumentation, 'laravel/configure', []);
            },
        );

        hook(
            ApplicationBuilder::class,
            'create',
            post: static function (ApplicationBuilder $builder, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        hook(
            RegisterProviders::class,
            'bootstrap',
            pre: static function (RegisterProviders $scope, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                SimpleSpan::pre($instrumentation, 'laravel/bootstrap/providers', []);
            },
            post: static function (RegisterProviders $scope, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        hook(
            BootProviders::class,
            'bootstrap',
            pre: static function (BootProviders $scope, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                SimpleSpan::pre($instrumentation, 'laravel/bootstrap/providers/boot', []);
            },
            post: static function (BootProviders $scope, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        hook(
            HandleExceptions::class,
            'bootstrap',
            pre: static function (HandleExceptions $scope, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                SimpleSpan::pre($instrumentation, 'laravel/bootstrap/exceptions', []);
            },
            post: static function (HandleExceptions $scope, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        hook(
            LoadConfiguration::class,
            'bootstrap',
            pre: static function (LoadConfiguration $scope, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                SimpleSpan::pre($instrumentation, 'laravel/bootstrap/config', []);
            },
            post: static function (LoadConfiguration $scope, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );
        hook(
            LoadEnvironmentVariables::class,
            'bootstrap',
            pre: static function (LoadEnvironmentVariables $scope, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                SimpleSpan::pre($instrumentation, 'laravel/bootstrap/env', []);
            },
            post: static function (LoadEnvironmentVariables $scope, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );
        hook(
            RegisterFacades::class,
            'bootstrap',
            pre: static function (RegisterFacades $scope, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                SimpleSpan::pre($instrumentation, 'laravel/bootstrap/facades', []);
            },
            post: static function (RegisterFacades $scope, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        hook(
            Request::class,
            'capture',
            pre: static function (mixed $request, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                SimpleSpan::pre($instrumentation, 'laravel/request/capture', []);
            },
            post: static function (mixed $request, array $params, mixed $response, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );
    }
}
