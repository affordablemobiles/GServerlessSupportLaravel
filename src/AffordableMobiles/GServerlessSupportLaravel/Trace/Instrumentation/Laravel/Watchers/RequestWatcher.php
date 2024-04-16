<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\Laravel\Watchers;

use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\SimpleSpan;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Route;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\SemConv\TraceAttributes;

use function OpenTelemetry\Instrumentation\hook;

class RequestWatcher extends Watcher
{
    public function __construct(
        private CachedInstrumentation $instrumentation,
    ) {}

    /** @psalm-suppress UndefinedInterfaceMethod */
    public function register(Application $app): void
    {
        // Grab middleware to trace & register required hooks dynamically at runtime.
        hook(
            Pipeline::class,
            'through',
            pre: function (Pipeline $pipeline, array $params, string $class, string $function, ?string $filename, ?int $lineno): void {
                foreach (($params[0] ?? []) as $p) {
                    if (\is_callable($p)) {
                        // Can't handle closures yet.
                    } elseif (!\is_object($p)) {
                        [$name, $parameters] = $this->parsePipeString($p);
                        $tracedMiddleware[]  = $name;
                        $this->registerMiddleware($name);
                    }
                    // Can't handle already objects yet.
                }
            },
        );

        hook(
            Route::class,
            'run',
            pre: function (Route $route, array $params, string $class, string $function, ?string $filename, ?int $lineno): void {
                $attributes = [];

                $class = $route->getControllerClass();
                if (!empty($class)) {
                    $attributes['callable']  = $class.'::'.$route->getControllerMethod();
                } else {
                    $attributes['callable'] = 'Closure';
                }

                SimpleSpan::pre(
                    $this->instrumentation,
                    'laravel/controller',
                    $attributes,
                );
            },
            post: static function (Route $route, array $params, mixed $response, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        $app['events']->listen(RouteMatched::class, static function (RouteMatched $event): void {
            /** @var null|SpanInterface $span */
            $span = $event->request->attributes->get(SpanInterface::class);

            if ($span) {
                $span->updateName("{$event->request->getMethod()} /".ltrim($event->route->uri, '/'));
                $span->setAttribute(TraceAttributes::HTTP_ROUTE, $event->route->uri);
            }
        });
    }

    /*
    | Taken from Illuminate\Pipeline\Pipeline
    | as the visibility was set to protected
    */
    protected function parsePipeString($pipe)
    {
        [$name, $parameters] = array_pad(explode(':', $pipe, 2), 2, []);

        if (\is_string($parameters)) {
            $parameters = explode(',', $parameters);
        }

        return [$name, $parameters];
    }

    protected function registerMiddleware($name): void
    {
        hook(
            $name,
            'handle',
            pre: function (mixed $middleware, array $params, string $class, string $function, ?string $filename, ?int $lineno): void {
                SimpleSpan::pre(
                    $this->instrumentation,
                    'laravel/middleware',
                    [
                        'name' => $middleware::class,
                    ],
                );
            },
            post: static function (mixed $middleware, array $params, mixed $response, ?\Throwable $exception): void {
                SimpleSpan::post();
            }
        );
    }
}
