<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\Datastore\GDS;

use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\SimpleSpan;
use GDS\Gateway\GRPCv1 as Gateway;
use GDS\Gateway\RESTv1 as GatewayREST;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;

use function OpenTelemetry\Instrumentation\hook;

class GDSInstrumentation
{
    /** @psalm-suppress ArgumentTypeCoercion */
    public const NAME = 'gds';

    public static function register(CachedInstrumentation $instrumentation): void
    {
        hook(
            Gateway::class,
            'execute',
            pre: static function (Gateway $client, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                SimpleSpan::pre($instrumentation, sprintf('GDS/execute/%s', $params[0] ?? 'unknown'), []);
            },
            post: static function (Gateway $client, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        hook(
            Gateway::class,
            'fetchByKeyPart',
            pre: static function (Gateway $client, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                SimpleSpan::pre($instrumentation, 'GDS/fetchByKeyPart', []);
            },
            post: static function (Gateway $client, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        hook(
            Gateway::class,
            'upsert',
            pre: static function (Gateway $client, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                SimpleSpan::pre($instrumentation, 'GDS/upsert', []);
            },
            post: static function (Gateway $client, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        hook(
            Gateway::class,
            'gql',
            pre: static function (Gateway $client, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                SimpleSpan::pre($instrumentation, 'GDS/gql', []);
            },
            post: static function (Gateway $client, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        hook(
            GatewayREST::class,
            'executePostRequest',
            pre: static function (GatewayREST $client, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                SimpleSpan::pre($instrumentation, sprintf('GDS/execute/%s', $params[0] ?? 'unknown'), []);
            },
            post: static function (GatewayREST $client, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        hook(
            GatewayREST::class,
            'fetchByKeyPart',
            pre: static function (GatewayREST $client, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                SimpleSpan::pre($instrumentation, 'GDS/fetchByKeyPart', []);
            },
            post: static function (GatewayREST $client, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        hook(
            GatewayREST::class,
            'upsert',
            pre: static function (GatewayREST $client, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                SimpleSpan::pre($instrumentation, 'GDS/upsert', []);
            },
            post: static function (GatewayREST $client, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        hook(
            GatewayREST::class,
            'gql',
            pre: static function (GatewayREST $client, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                SimpleSpan::pre($instrumentation, 'GDS/gql', []);
            },
            post: static function (GatewayREST $client, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );
    }
}
