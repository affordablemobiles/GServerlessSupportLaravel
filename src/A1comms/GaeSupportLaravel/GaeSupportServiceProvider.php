<?php

namespace A1comms\GaeSupportLaravel;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Session;
use League\Flysystem\Filesystem as Flysystem;
use A1comms\GaeSupportLaravel\Session\DatastoreSessionHandler;
use A1comms\GaeSupportLaravel\Filesystem\GaeAdapter as GaeFilesystemAdapter;

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

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\GaePrepareCommand::class,
            ]);
        }

        // Register the DatastoreSessionHandler
        Session::extend('gae', function($app) {
            return new DatastoreSessionHandler;
        });

        Storage::extend('gae', function ($app, $config) {
            return new Flysystem(new GaeFilesystemAdapter($config['root']));
        });

        // register the package's routes
        require __DIR__.'/Http/routes.php';
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
