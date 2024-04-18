<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\Laravel\Watchers;

use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\SimpleSpan;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
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
            Request::class,
            'capture',
            pre: function (mixed $request, array $params, string $class, string $function, ?string $filename, ?int $lineno): void {
                SimpleSpan::pre($this->instrumentation, 'laravel/request/capture', []);
            },
            post: static function (mixed $request, array $params, mixed $response, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        // Trace when routing begins,
        // making it possible to infer how long the routing took by looking at when
        // the middleware or the controller execution begin.
        hook(
            Router::class,
            'dispatch',
            pre: function (Router $router, array $params, string $class, string $function, ?string $filename, ?int $lineno): void {
                SimpleSpan::pre($this->instrumentation, 'laravel/router', []);
            },
            post: static function (Router $router, array $params, mixed $response, ?\Throwable $exception): void {
                SimpleSpan::post();
            },
        );

        hook(
            Route::class,
            'run',
            pre: function (Route $route, array $params, string $class, string $function, ?string $filename, ?int $lineno): void {
                $attributes = [];

                try {
                    $class = $route->getControllerClass();
                    if (!empty($class)) {
                        $callback                = Str::parseCallback($route->action['uses']);
                        $attributes['callable']  = $class.'::'.$callback[1];
                    } else {
                        $attributes['callable'] = 'Closure';
                    }
                } catch (\Throwable $ex) {
                    report($ex);
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

        hook(
            Response::class,
            'send',
            pre: function (Response $resp, array $params, string $class, string $function, ?string $filename, ?int $lineno): void {
                SimpleSpan::pre($this->instrumentation, 'laravel/response/send', []);
            },
            post: static function (Response $resp, array $params, mixed $response, ?\Throwable $exception): void {
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
