<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\Laravel\Watchers;

use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\SimpleSpan;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Foundation\Application;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;

use function OpenTelemetry\Instrumentation\hook;

class AuthenticationWatcher extends Watcher
{
    public function __construct(
        private CachedInstrumentation $instrumentation,
    ) {}

    /** @psalm-suppress UndefinedInterfaceMethod */
    public function register(Application $app): void
    {
        hook(
            Authenticate::class,
            'authenticate',
            pre: function (Authenticate $auth, array $params, string $class, string $function, ?string $filename, ?int $lineno): void {
                $guards = $params[1] ?? [];

                if (!empty($guards)) {
                    SimpleSpan::pre($this->instrumentation, 'laravel/auth', ['guards' => $guards]);
                }
            },
            post: static function (Authenticate $auth, array $params, mixed $response, ?\Throwable $exception): void {
                $guards = $params[1] ?? [];

                if (!empty($guards)) {
                    SimpleSpan::post();
                }
            },
        );
    }
}
