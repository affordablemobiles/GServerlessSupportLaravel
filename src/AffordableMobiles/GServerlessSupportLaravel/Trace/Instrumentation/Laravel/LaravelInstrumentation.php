<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\Laravel;

use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\Laravel\Watchers\AuthenticationWatcher;
use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\Laravel\Watchers\CacheWatcher;
use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\Laravel\Watchers\ClientRequestWatcher;
use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\Laravel\Watchers\QueryWatcher;
use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\Laravel\Watchers\RequestWatcher;
use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\Laravel\Watchers\SessionWatcher;
use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\Laravel\Watchers\ViewWatcher;
use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\Laravel\Watchers\Watcher;
use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\SimpleSpan;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Console\ServeCommand;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;

use function OpenTelemetry\Instrumentation\hook;

class LaravelInstrumentation
{
    public const NAME = 'laravel';

    public static function registerWatchers(Application $app, Watcher $watcher): void
    {
        $watcher->register($app);
    }

    public static function register(CachedInstrumentation $instrumentation): void
    {
        hook(
            Application::class,
            '__construct',
            post: static function (Application $application, array $params, mixed $returnValue, ?\Throwable $exception) use ($instrumentation): void {
                self::registerWatchers($application, new ViewWatcher($instrumentation));
                self::registerWatchers($application, new SessionWatcher($instrumentation));
                self::registerWatchers($application, new CacheWatcher());
                self::registerWatchers($application, new ClientRequestWatcher($instrumentation));
                self::registerWatchers($application, new QueryWatcher($instrumentation));
                self::registerWatchers($application, new RequestWatcher($instrumentation));
                self::registerWatchers($application, new AuthenticationWatcher($instrumentation));
            },
        );

        hook(
            Kernel::class,
            'terminate',
            pre: static function (Kernel $kernel, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
                SimpleSpan::pre($instrumentation, 'laravel/terminate', []);
            },
            post: static function (Kernel $kernel, array $params, mixed $response, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        HttpInstrumentation::register($instrumentation);

        self::developmentInstrumentation();
    }

    private static function developmentInstrumentation(): void
    {
        // Allow instrumentation when using the local PHP development server.
        if (class_exists(ServeCommand::class) && property_exists(ServeCommand::class, 'passthroughVariables')) {
            hook(
                ServeCommand::class,
                'handle',
                pre: static function (ServeCommand $serveCommand, array $params, string $class, string $function, ?string $filename, ?int $lineno): void {
                    foreach ($_ENV as $key => $value) {
                        if (str_starts_with($key, 'OTEL_') && !\in_array($key, ServeCommand::$passthroughVariables, true)) {
                            ServeCommand::$passthroughVariables[] = $key;
                        }
                    }
                },
            );
        }
    }
}
