<?php

namespace A1comms\GaeSupportLaravel;

use Illuminate\Support\ServiceProvider;

/**
 * Class GaeSupportServiceProvider
 *
 * @package A1comms\GaeSupportLaravel
 */
class GaeSupportServiceProvider extends ServiceProvider
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
        $this->mergeConfigFrom(
            __DIR__.'/../../config/gaesupport.php', 'gaesupport'
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish our config file when the user runs "artisan vendor:publish".
        $this->publishes([
            __DIR__.'/../../config/gaesupport.php' => config_path('gaesupport.php'),
        ]);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array('gae-support');
    }
}
