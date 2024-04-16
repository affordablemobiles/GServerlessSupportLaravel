<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\Laravel\Watchers;

use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\SimpleSpan;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Session\Store;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;

use function OpenTelemetry\Instrumentation\hook;

class SessionWatcher extends Watcher
{
    public function __construct(
        private CachedInstrumentation $instrumentation,
    ) {}

    public function register(Application $app): void
    {
        hook(
            Store::class,
            'start',
            pre: function (Store $store, array $params, string $class, string $function, ?string $filename, ?int $lineno): void {
                SimpleSpan::pre($this->instrumentation, 'laravel/session/start', []);
            },
            post: static function (Store $store, array $params, mixed $response, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        hook(
            Store::class,
            'save',
            pre: function (Store $store, array $params, string $class, string $function, ?string $filename, ?int $lineno): void {
                SimpleSpan::pre($this->instrumentation, 'laravel/session/save', []);
            },
            post: static function (Store $store, array $params, mixed $response, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        hook(
            Store::class,
            'migrate',
            pre: function (Store $store, array $params, string $class, string $function, ?string $filename, ?int $lineno): void {
                $destroy = $params[0] ?? false;
                if (!$destroy) {
                    return;
                }

                SimpleSpan::pre($this->instrumentation, 'laravel/session/destroy', []);
            },
            post: static function (Store $store, array $params, mixed $response, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );
    }
}
