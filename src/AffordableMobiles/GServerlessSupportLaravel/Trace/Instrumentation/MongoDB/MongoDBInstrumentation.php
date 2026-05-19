<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\MongoDB;

use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\InstrumentationInterface;
use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\SimpleSpan;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\Laravel\Connection;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\SemConv\TraceAttributes;

use function OpenTelemetry\Instrumentation\hook;

class MongoDBInstrumentation implements InstrumentationInterface
{
    public const NAME = 'mongodb';

    public static function register(CachedInstrumentation $instrumentation): void
    {
        if (!class_exists(Collection::class)) {
            return;
        }

        self::registerConnectionHooks($instrumentation);
        self::registerCollectionHooks($instrumentation);
        self::registerDatabaseHooks($instrumentation);
    }

    private static function registerConnectionHooks(CachedInstrumentation $instrumentation): void
    {
        // Hook the Laravel MongoDB Connection constructor to trace initial connection setup.
        // MongoDB\Laravel\Connection::__construct() creates the Client and selects the database.
        hook(
            Connection::class,
            '__construct',
            pre: static function (Connection $connection, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                try {
                    $config = $params[0] ?? [];

                    SimpleSpan::pre($instrumentation, 'mongodb/connect', array_filter([
                        TraceAttributes::DB_SYSTEM    => 'mongodb',
                        TraceAttributes::DB_NAMESPACE => $config['database'] ?? null,
                        'db.connection.name'          => $config['name']     ?? null,
                        'db.host'                     => \is_array($config['host'] ?? null)
                            ? implode(',', $config['host'])
                            : ($config['host'] ?? null),
                        'db.port'                     => $config['port'] ?? null,
                    ]));
                } catch (\Throwable $ex) {
                    report($ex);

                    SimpleSpan::pre($instrumentation, 'mongodb/connect');
                }
            },
            post: static function (Connection $connection, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        // Hook disconnect to trace connection teardown.
        hook(
            Connection::class,
            'disconnect',
            pre: static function (Connection $connection, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                try {
                    SimpleSpan::pre($instrumentation, 'mongodb/disconnect', array_filter([
                        TraceAttributes::DB_SYSTEM    => 'mongodb',
                        TraceAttributes::DB_NAMESPACE => $connection->getDatabaseName(),
                    ]));
                } catch (\Throwable $ex) {
                    report($ex);

                    SimpleSpan::pre($instrumentation, 'mongodb/disconnect');
                }
            },
            post: static function (Connection $connection, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        // Hook reconnect to trace reconnection attempts — critical for serverless cold starts.
        hook(
            Connection::class,
            'reconnect',
            pre: static function (Connection $connection, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                try {
                    SimpleSpan::pre($instrumentation, 'mongodb/reconnect', array_filter([
                        TraceAttributes::DB_SYSTEM    => 'mongodb',
                        TraceAttributes::DB_NAMESPACE => $connection->getDatabaseName(),
                    ]));
                } catch (\Throwable $ex) {
                    report($ex);

                    SimpleSpan::pre($instrumentation, 'mongodb/reconnect');
                }
            },
            post: static function (Connection $connection, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        // Hook ping to trace health checks — useful for monitoring connection liveness.
        hook(
            Connection::class,
            'ping',
            pre: static function (Connection $connection, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                try {
                    SimpleSpan::pre($instrumentation, 'mongodb/ping', array_filter([
                        TraceAttributes::DB_SYSTEM    => 'mongodb',
                        TraceAttributes::DB_NAMESPACE => $connection->getDatabaseName(),
                    ]));
                } catch (\Throwable $ex) {
                    report($ex);

                    SimpleSpan::pre($instrumentation, 'mongodb/ping');
                }
            },
            post: static function (Connection $connection, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        // Hook the low-level Client constructor to trace the actual MongoDB driver-level connection.
        // This is where MongoDB\Driver\Manager is instantiated and the connection URI is parsed.
        hook(
            Client::class,
            '__construct',
            pre: static function (Client $client, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                try {
                    $uri = $params[0] ?? Client::DEFAULT_URI;

                    // Extract host from URI without exposing credentials
                    $host = null;

                    try {
                        $parsed = parse_url($uri);
                        $host   = $parsed['host'] ?? null;
                    } catch (\Throwable) {
                        // ignore parse failures
                    }

                    SimpleSpan::pre($instrumentation, 'mongodb/client/connect', array_filter([
                        TraceAttributes::DB_SYSTEM => 'mongodb',
                        'db.host'                  => $host,
                    ]));
                } catch (\Throwable $ex) {
                    report($ex);

                    SimpleSpan::pre($instrumentation, 'mongodb/client/connect');
                }
            },
            post: static function (Client $client, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );
    }

    private static function registerCollectionHooks(CachedInstrumentation $instrumentation): void
    {
        // Read operations on Collection
        $readOperations = [
            'aggregate',
            'count',
            'countDocuments',
            'distinct',
            'estimatedDocumentCount',
            'find',
            'findOne',
        ];

        foreach ($readOperations as $operation) {
            hook(
                Collection::class,
                $operation,
                pre: static function (Collection $collection, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation, $operation): void {
                    try {
                        $attributes               = self::collectionAttributes($collection, $operation);
                        $attributes['stackTrace'] = serialize(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

                        if ('aggregate' === $operation) {
                            $attributes['db.query.text'] = self::safeJsonEncode($params[0] ?? []);
                        } elseif (\in_array($operation, ['find', 'findOne', 'count', 'countDocuments'], true)) {
                            $attributes['db.query.text'] = self::safeJsonEncode($params[0] ?? []);
                        } elseif ('distinct' === $operation) {
                            $attributes['db.query.text'] = $params[0] ?? '';
                        }

                        SimpleSpan::pre($instrumentation, self::spanName($collection, $operation), $attributes);
                    } catch (\Throwable $ex) {
                        report($ex);

                        SimpleSpan::pre($instrumentation, self::spanName($collection, $operation));
                    }
                },
                post: static function (Collection $collection, array $params, mixed $returnValue, ?\Throwable $exception): void {
                    SimpleSpan::post();
                },
            );
        }

        // Write operations on Collection
        $writeOperations = [
            'insertOne',
            'insertMany',
            'updateOne',
            'updateMany',
            'deleteOne',
            'deleteMany',
            'replaceOne',
            'findOneAndDelete',
            'findOneAndReplace',
            'findOneAndUpdate',
            'bulkWrite',
        ];

        foreach ($writeOperations as $operation) {
            hook(
                Collection::class,
                $operation,
                pre: static function (Collection $collection, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation, $operation): void {
                    try {
                        $attributes               = self::collectionAttributes($collection, $operation);
                        $attributes['stackTrace'] = serialize(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

                        // Capture filter for operations that have one
                        if (\in_array($operation, ['updateOne', 'updateMany', 'deleteOne', 'deleteMany', 'replaceOne', 'findOneAndDelete', 'findOneAndReplace', 'findOneAndUpdate'], true)) {
                            $attributes['db.query.text'] = self::safeJsonEncode($params[0] ?? []);
                        }

                        // Capture batch size for bulk operations
                        if ('bulkWrite' === $operation) {
                            $attributes[TraceAttributes::DB_OPERATION_BATCH_SIZE] = \count($params[0] ?? []);
                        } elseif ('insertMany' === $operation) {
                            $attributes[TraceAttributes::DB_OPERATION_BATCH_SIZE] = \count($params[0] ?? []);
                        }

                        SimpleSpan::pre($instrumentation, self::spanName($collection, $operation), $attributes);
                    } catch (\Throwable $ex) {
                        report($ex);

                        SimpleSpan::pre($instrumentation, self::spanName($collection, $operation));
                    }
                },
                post: static function (Collection $collection, array $params, mixed $returnValue, ?\Throwable $exception): void {
                    SimpleSpan::post();
                },
            );
        }

        // Index operations
        $indexOperations = [
            'createIndex',
            'createIndexes',
            'dropIndex',
            'dropIndexes',
            'listIndexes',
        ];

        foreach ($indexOperations as $operation) {
            hook(
                Collection::class,
                $operation,
                pre: static function (Collection $collection, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation, $operation): void {
                    try {
                        $attributes               = self::collectionAttributes($collection, $operation);
                        $attributes['stackTrace'] = serialize(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

                        SimpleSpan::pre($instrumentation, self::spanName($collection, $operation), $attributes);
                    } catch (\Throwable $ex) {
                        report($ex);

                        SimpleSpan::pre($instrumentation, self::spanName($collection, $operation));
                    }
                },
                post: static function (Collection $collection, array $params, mixed $returnValue, ?\Throwable $exception): void {
                    SimpleSpan::post();
                },
            );
        }

        // Collection admin: drop, rename
        hook(
            Collection::class,
            'drop',
            pre: static function (Collection $collection, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                try {
                    $attributes               = self::collectionAttributes($collection, 'drop');
                    $attributes['stackTrace'] = serialize(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

                    SimpleSpan::pre($instrumentation, self::spanName($collection, 'drop'), $attributes);
                } catch (\Throwable $ex) {
                    report($ex);

                    SimpleSpan::pre($instrumentation, self::spanName($collection, 'drop'));
                }
            },
            post: static function (Collection $collection, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        hook(
            Collection::class,
            'rename',
            pre: static function (Collection $collection, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                try {
                    $attributes                                = self::collectionAttributes($collection, 'rename');
                    $attributes['stackTrace']                  = serialize(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
                    $attributes['db.target_collection.name']   = $params[0] ?? '';

                    SimpleSpan::pre($instrumentation, self::spanName($collection, 'rename'), $attributes);
                } catch (\Throwable $ex) {
                    report($ex);

                    SimpleSpan::pre($instrumentation, self::spanName($collection, 'rename'));
                }
            },
            post: static function (Collection $collection, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );
    }

    private static function registerDatabaseHooks(CachedInstrumentation $instrumentation): void
    {
        hook(
            Database::class,
            'aggregate',
            pre: static function (Database $database, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                try {
                    $attributes                  = self::databaseAttributes($database, 'aggregate');
                    $attributes['stackTrace']    = serialize(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
                    $attributes['db.query.text'] = self::safeJsonEncode($params[0] ?? []);

                    SimpleSpan::pre($instrumentation, self::databaseSpanName($database, 'aggregate'), $attributes);
                } catch (\Throwable $ex) {
                    report($ex);

                    SimpleSpan::pre($instrumentation, self::databaseSpanName($database, 'aggregate'));
                }
            },
            post: static function (Database $database, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        hook(
            Database::class,
            'command',
            pre: static function (Database $database, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                try {
                    $attributes                  = self::databaseAttributes($database, 'command');
                    $attributes['stackTrace']    = serialize(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
                    $attributes['db.query.text'] = self::safeJsonEncode($params[0] ?? []);

                    SimpleSpan::pre($instrumentation, self::databaseSpanName($database, 'command'), $attributes);
                } catch (\Throwable $ex) {
                    report($ex);

                    SimpleSpan::pre($instrumentation, self::databaseSpanName($database, 'command'));
                }
            },
            post: static function (Database $database, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        hook(
            Database::class,
            'createCollection',
            pre: static function (Database $database, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                try {
                    $attributes                                      = self::databaseAttributes($database, 'createCollection');
                    $attributes['stackTrace']                        = serialize(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
                    $attributes[TraceAttributes::DB_COLLECTION_NAME] = $params[0] ?? '';

                    SimpleSpan::pre($instrumentation, self::databaseSpanName($database, 'createCollection'), $attributes);
                } catch (\Throwable $ex) {
                    report($ex);

                    SimpleSpan::pre($instrumentation, self::databaseSpanName($database, 'createCollection'));
                }
            },
            post: static function (Database $database, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        hook(
            Database::class,
            'drop',
            pre: static function (Database $database, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                try {
                    $attributes               = self::databaseAttributes($database, 'drop');
                    $attributes['stackTrace'] = serialize(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

                    SimpleSpan::pre($instrumentation, self::databaseSpanName($database, 'drop'), $attributes);
                } catch (\Throwable $ex) {
                    report($ex);

                    SimpleSpan::pre($instrumentation, self::databaseSpanName($database, 'drop'));
                }
            },
            post: static function (Database $database, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        hook(
            Database::class,
            'dropCollection',
            pre: static function (Database $database, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                try {
                    $attributes                                      = self::databaseAttributes($database, 'dropCollection');
                    $attributes['stackTrace']                        = serialize(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
                    $attributes[TraceAttributes::DB_COLLECTION_NAME] = $params[0] ?? '';

                    SimpleSpan::pre($instrumentation, self::databaseSpanName($database, 'dropCollection'), $attributes);
                } catch (\Throwable $ex) {
                    report($ex);

                    SimpleSpan::pre($instrumentation, self::databaseSpanName($database, 'dropCollection'));
                }
            },
            post: static function (Database $database, array $params, mixed $returnValue, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        $listOperations = [
            'listCollections',
            'listCollectionNames',
        ];

        foreach ($listOperations as $operation) {
            hook(
                Database::class,
                $operation,
                pre: static function (Database $database, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation, $operation): void {
                    try {
                        $attributes               = self::databaseAttributes($database, $operation);
                        $attributes['stackTrace'] = serialize(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

                        SimpleSpan::pre($instrumentation, self::databaseSpanName($database, $operation), $attributes);
                    } catch (\Throwable $ex) {
                        report($ex);

                        SimpleSpan::pre($instrumentation, self::databaseSpanName($database, $operation));
                    }
                },
                post: static function (Database $database, array $params, mixed $returnValue, ?\Throwable $exception): void {
                    SimpleSpan::post();
                },
            );
        }
    }

    /**
     * Build common span attributes for Collection operations.
     */
    private static function collectionAttributes(Collection $collection, string $operation): array
    {
        return array_filter([
            TraceAttributes::DB_SYSTEM           => 'mongodb',
            TraceAttributes::DB_NAMESPACE        => $collection->getDatabaseName(),
            TraceAttributes::DB_COLLECTION_NAME  => $collection->getCollectionName(),
            TraceAttributes::DB_OPERATION_NAME   => $operation,
        ]);
    }

    /**
     * Build common span attributes for Database operations.
     */
    private static function databaseAttributes(Database $database, string $operation): array
    {
        return array_filter([
            TraceAttributes::DB_SYSTEM         => 'mongodb',
            TraceAttributes::DB_NAMESPACE      => $database->getDatabaseName(),
            TraceAttributes::DB_OPERATION_NAME => $operation,
        ]);
    }

    /**
     * Build span name for Collection operations: "mongodb/{operation}".
     */
    private static function spanName(Collection $collection, string $operation): string
    {
        return \sprintf('mongodb/%s', $operation);
    }

    /**
     * Build span name for Database operations: "mongodb/{operation}".
     */
    private static function databaseSpanName(Database $database, string $operation): string
    {
        return \sprintf('mongodb/%s', $operation);
    }

    /**
     * Safely JSON-encode a value for trace attributes, handling objects and arrays.
     */
    private static function safeJsonEncode(mixed $value): string
    {
        try {
            if ($value instanceof BSONDocument || $value instanceof BSONArray) {
                $value = $value->jsonSerialize();
            }

            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '{}';
        } catch (\Throwable) {
            return '{}';
        }
    }
}
