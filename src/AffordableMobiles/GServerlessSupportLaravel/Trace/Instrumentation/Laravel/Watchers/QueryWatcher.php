<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\Laravel\Watchers;

use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\SimpleSpan;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Connection;
use Illuminate\Database\Connectors\ConnectorInterface;
use Illuminate\Support\Str;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\SemConv\TraceAttributes;

use function OpenTelemetry\Instrumentation\hook;

class QueryWatcher extends Watcher
{
    public function __construct(
        private CachedInstrumentation $instrumentation,
    ) {}

    /** @psalm-suppress UndefinedInterfaceMethod */
    public function register(Application $app): void
    {
        hook(
            ConnectorInterface::class,
            'connect',
            pre: function (ConnectorInterface $conn, array $params, string $class, string $function, ?string $filename, ?int $lineno): void {
                SimpleSpan::pre($this->instrumentation, 'sql CONNECT', [
                    'driver'      => $params[0]['driver']      ?? 'unknown',
                    'host'        => $params[0]['host']        ?? '',
                    'unix_socket' => $params[0]['unix_socket'] ?? '',
                ]);
            },
            post: static function (ConnectorInterface $conn, array $params, mixed $response, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        hook(
            Connection::class,
            'runQueryCallback',
            pre: function (Connection $conn, array $params, string $class, string $function, ?string $filename, ?int $lineno): void {
                try {
                    $sql = $params[0];

                    $operationName = Str::upper(Str::before($sql, ' '));
                    if (!\in_array($operationName, ['SELECT', 'INSERT', 'UPDATE', 'DELETE'], true)) {
                        $operationName = null;
                    }

                    SimpleSpan::pre($this->instrumentation, 'sql '.$operationName, [
                        TraceAttributes::DB_SYSTEM    => $conn->getDriverName(),
                        TraceAttributes::DB_NAME      => $conn->getDatabaseName(),
                        TraceAttributes::DB_OPERATION => $operationName,
                        TraceAttributes::DB_USER      => $conn->getConfig('username'),
                        TraceAttributes::DB_STATEMENT => $sql,
                    ]);
                } catch (\Throwable $e) {
                    report($e);
                }
            },
            post: static function (Connection $conn, array $params, mixed $response, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );
    }
}
