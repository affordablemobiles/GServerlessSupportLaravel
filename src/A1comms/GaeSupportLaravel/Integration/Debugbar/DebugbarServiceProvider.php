<?php

namespace A1comms\GaeSupportLaravel\Integration\Debugbar;

use Illuminate\Support\ServiceProvider;
use Barryvdh\Debugbar\Facades\Debugbar;

/**
 * Class DebugbarServiceProvider
 *
 * @package A1comms\GaeSupportLaravel
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
     *
     * @return void
     */
    public function register()
    {
        if (class_exists(Debugbar::class)) {
            Debugbar::addCollector(new TimeDataCollector());
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['gae-support-debugbar'];
    }
}
