<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Integration\HttpClient;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Token\Middleware\AuthTokenMiddleware;
use AffordableMobiles\GServerlessSupportLaravel\Integration\Guzzle\GuzzleRetryMiddleware;
use AffordableMobiles\GServerlessSupportLaravel\Integration\HttpClient\Listeners\OutboundHttpTelemetryListener;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

class HttpClientServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../../../config/gserverlesssupport.php',
            'gserverlesssupport'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(Dispatcher $events): void
    {
        $config = $this->app->make('config');

        // 1. Global retry middleware for GCP serverless network / connect timeouts
        if ($config->get('gserverlesssupport.http-client.retry.enabled', true)) {
            Http::globalMiddleware(GuzzleRetryMiddleware::factory());
        }

        // 2. Global Google Auth token injection for microservice calls
        if ($config->get('gserverlesssupport.http-client.auth.enabled', true)) {
            Http::globalMiddleware(static fn (callable $handler): callable => AuthTokenMiddleware::factory()($handler));
        }

        // 3. Automated Error Reporting telemetry for outbound HTTP calls
        if ($config->get('gserverlesssupport.http-client.telemetry.enabled', true)) {
            $events->subscribe(OutboundHttpTelemetryListener::class);
        }
    }
}
