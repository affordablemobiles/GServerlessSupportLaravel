<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\Laravel\Watchers;

use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\SimpleSpan;
use AffordableMobiles\GServerlessSupportLaravel\View\Engines\CompilerEngine as PrecompiledEngine;
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
            pre: function (CompilerEngine $engine, array $params, string $class, string $function, ?string $filename, ?int $lineno): void {
                SimpleSpan::pre(
                    $this->instrumentation,
                    'laravel/view',
                    [
                        'path' => $params[0] ?? 'unknown',
                    ],
                );
            },
            post: static function (CompilerEngine $engine, array $params, mixed $response, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        hook(
            PrecompiledEngine::class,
            'get',
            pre: function (PrecompiledEngine $engine, array $params, string $class, string $function, ?string $filename, ?int $lineno): void {
                SimpleSpan::pre(
                    $this->instrumentation,
                    'laravel/view',
                    [
                        'path'         => $params[0] ?? 'unknown',
                        'pre-compiled' => true,
                    ],
                );
            },
            post: static function (PrecompiledEngine $engine, array $params, mixed $response, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );
    }
}
