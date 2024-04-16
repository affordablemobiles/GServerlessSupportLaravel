<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\Laravel\Watchers;

use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\SimpleSpan;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\View\Engines\CompilerEngine;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;

use function OpenTelemetry\Instrumentation\hook;

class ViewWatcher extends Watcher
{
    public function __construct(
        private CachedInstrumentation $instrumentation,
    ) {}

    public function register(Application $app): void
    {
        hook(
            CompilerEngine::class,
            'get',
            pre: static function (CompilerEngine $engine, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                SimpleSpan::pre(
                    $instrumentation,
                    'laravel/view',
                    [
                        'path' => $params[0] ?? 'unknown',
                    ],
                );
            },
            post: static function (CompilerEngine $engine, array $params, ?mixed $response, ?\Throwable $exception): void {
                SimpleSpan::post();
            }
        );
    }
}
