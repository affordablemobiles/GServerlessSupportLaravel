<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\Datastore\Eloquent;

use AffordableMobiles\EloquentDatastore\Client\DatastoreClient;
use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\InstrumentationInterface;
use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\SimpleSpan;
use Google\Cloud\Datastore\DatastoreClient as BaseDatastoreClient;
use Google\Cloud\Datastore\Key;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;

use function OpenTelemetry\Instrumentation\hook;

class EloquentDatastoreInstrumentation implements InstrumentationInterface
{
    /** @psalm-suppress ArgumentTypeCoercion */
    public const NAME = 'eloquent-datastore';

    public static function register(CachedInstrumentation $instrumentation): void
    {
        hook(
            BaseDatastoreClient::class,
            'lookupBatch',
            pre: static function (BaseDatastoreClient $client, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                $batch   = $params[0];
                $options = $params[1] ?? [];

                $attributes = self::getAttributes($batch, $options);

                $attributes['stackTrace'] = serialize(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

                SimpleSpan::pre($instrumentation, 'datastore/lookup', $attributes);
            },
            post: static function (BaseDatastoreClient $client, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        hook(
            BaseDatastoreClient::class,
            'runQuery',
            pre: static function (BaseDatastoreClient $client, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                $attributes = [];

                try {
                    $query   = $params[0];
                    $options = $params[1] ?? [];

                    $type = $query->queryKey();

                    $attributes = [
                        'namespace'  => $options['namespaceId'] ?? null,
                        'database'   => $options['databaseId']  ?? null,
                        'project'    => $options['projectId']   ?? null,
                        'type'       => $type,
                        'stackTrace' => serialize(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)),
                    ];

                    switch ($type) {
                        case 'gqlQuery':
                            $attributes['queryString'] = $query->queryObject()['queryString'] ?? null;

                            break;

                        case 'query':
                            $kinds = [];

                            $object = $query->queryObject();
                            foreach ($object['kind'] as $kind) {
                                $kinds[] = $kind['name'] ?? null;
                            }

                            $attributes['kind'] = array_filter(array_unique($kinds));

                            break;

                        default:
                            break;
                    }

                    $attributes = array_filter($attributes);
                } catch (\Throwable $ex) {
                    report($ex);
                }

                SimpleSpan::pre($instrumentation, 'datastore/runQuery', $attributes);
            },
            post: static function (BaseDatastoreClient $client, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        hook(
            DatastoreClient::class,
            'insertBatch',
            pre: static function (DatastoreClient $client, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                $batch   = $params[0];
                $options = $params[1] ?? [];

                $attributes = self::getAttributes($batch, $options);

                $attributes['stackTrace'] = serialize(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

                SimpleSpan::pre($instrumentation, 'datastore/insert', $attributes);
            },
            post: static function (DatastoreClient $client, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        hook(
            DatastoreClient::class,
            'upsertBatch',
            pre: static function (DatastoreClient $client, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                $batch   = $params[0];
                $options = $params[1] ?? [];

                $attributes = self::getAttributes($batch, $options);

                $attributes['stackTrace'] = serialize(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

                SimpleSpan::pre($instrumentation, 'datastore/upsert', $attributes);
            },
            post: static function (DatastoreClient $client, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        hook(
            DatastoreClient::class,
            'deleteBatch',
            pre: static function (DatastoreClient $client, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                $batch   = $params[0];
                $options = $params[1] ?? [];

                $attributes = self::getAttributes($batch, $options);

                $attributes['stackTrace'] = serialize(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

                SimpleSpan::pre($instrumentation, 'datastore/delete', $attributes);
            },
            post: static function (DatastoreClient $client, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );
    }

    protected static function getAttributes(array $batch, array $options): array
    {
        try {
            $attributes = [];

            $kinds      = [];
            $namespaces = [];
            $databases  = [];
            $projects   = [];

            $namespaces = $options['namespaceId'] ?? null;
            $databases  = $options['databaseId']  ?? null;
            $projects   = $options['projectId']   ?? null;

            foreach ($batch as $item) {
                $key = $item instanceof Key ? $item : $item->key();
                $key = $key->keyObject();

                $kinds[]      = $key['path'][0]['kind']            ?? null;
                $namespaces[] = $key['partitionId']['namespaceId'] ?? null;
                $databases[]  = $key['partitionId']['databaseId']  ?? null;
                $projects[]   = $key['partitionId']['projectId']   ?? null;
            }

            $kinds      = array_filter(array_unique($kinds));
            $namespaces = array_filter(array_unique($namespaces));
            $databases  = array_filter(array_unique($databases));
            $projects   = array_filter(array_unique($projects));

            $attributes = [
                'kind'      => $kinds,
                'namespace' => $namespaces,
                'database'  => $databases,
                'project'   => $projects,
            ];

            return array_filter($attributes);
        } catch (\Throwable $ex) {
            report($ex);

            return [];
        }
    }
}
