<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\Datastore\Eloquent;

use AffordableMobiles\EloquentDatastore\Client\DatastoreClient;
use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\InstrumentationInterface;
use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\SimpleSpan;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;

use function OpenTelemetry\Instrumentation\hook;

class EloquentDatastoreInstrumentation implements InstrumentationInterface
{
    /** @psalm-suppress ArgumentTypeCoercion */
    public const NAME = 'eloquent-datastore';

    public static function register(CachedInstrumentation $instrumentation): void
    {
        hook(
            DatastoreClient::class,
            'lookupBatch',
            pre: static function (DatastoreClient $client, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                SimpleSpan::pre($instrumentation, 'datastore/lookup', []);
            },
            post: static function (DatastoreClient $client, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        hook(
            DatastoreClient::class,
            'runQuery',
            pre: static function (DatastoreClient $client, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                SimpleSpan::pre($instrumentation, 'datastore/runQuery', []);
            },
            post: static function (DatastoreClient $client, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        hook(
            DatastoreClient::class,
            'insertBatch',
            pre: static function (DatastoreClient $client, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                SimpleSpan::pre($instrumentation, 'datastore/insert', []);
            },
            post: static function (DatastoreClient $client, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        hook(
            DatastoreClient::class,
            'upsertBatch',
            pre: static function (DatastoreClient $client, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                SimpleSpan::pre($instrumentation, 'datastore/upsert', []);
            },
            post: static function (DatastoreClient $client, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        hook(
            DatastoreClient::class,
            'deleteBatch',
            pre: static function (DatastoreClient $client, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                SimpleSpan::pre($instrumentation, 'datastore/delete', []);
            },
            post: static function (DatastoreClient $client, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );
    }
}
