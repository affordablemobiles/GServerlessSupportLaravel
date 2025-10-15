<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Integration\ErrorReporting\ClientSideJavaScript;

use AffordableMobiles\GServerlessSupportLaravel\Integration\ErrorReporting\ClientSideJavaScript\Http\Middleware\AddPreloadHeaders;
use AffordableMobiles\GServerlessSupportLaravel\Integration\ErrorReporting\ClientSideJavaScript\Services\PreloadService;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ClientSideJavaScriptErrorReportingServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../../../../config/js-error-reporter.php',
            'js-error-reporter'
        );

        // Bind the PreloadService as a singleton for the request lifecycle.
        $this->app->singleton(PreloadService::class, static fn ($app) => new PreloadService());
    }

    public function boot(Router $router): void
    {
        $this->loadViewsFrom(__DIR__.'/../../../../../resources/views/js-error-reporting', 'gss-js-error-reporting');

        $router->pushMiddlewareToGroup('web', AddPreloadHeaders::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../../../../config/js-error-reporter.php' => config_path('js-error-reporter.php'),
            ], 'js-error-reporter-config');

            $this->publishes([
                __DIR__.'/../../../../../resources/views/js-error-reporting' => resource_path('views/vendor/g-serverless-support/js-error-reporting'),
            ], 'gss-js-error-reporter-views');
        }

        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        if (config('js-error-reporter.enabled')) {
            Route::group($this->routeConfiguration(), function (): void {
                $this->loadRoutesFrom(__DIR__.'/../../../../../routes/js-error-report.php');
            });
        }
    }

    /**
     * Get the package route group configuration array.
     */
    protected function routeConfiguration(): array
    {
        return [
            'prefix'     => config('js-error-reporter.route_prefix'),
            'middleware' => config('js-error-reporter.route_middleware'),
        ];
    }
}
