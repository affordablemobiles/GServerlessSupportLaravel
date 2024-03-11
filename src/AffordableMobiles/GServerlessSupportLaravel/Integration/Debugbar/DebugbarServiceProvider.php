<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Integration\Debugbar;

use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Support\ServiceProvider;

/**
 * Class DebugbarServiceProvider.
 */
class DebugbarServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Register bindings in the container.
     */
    public function register(): void
    {
        if (class_exists(Debugbar::class)) {
            Debugbar::addCollector(new TimeDataCollector());
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {}

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['g-serverless-support-debugbar'];
    }
}
