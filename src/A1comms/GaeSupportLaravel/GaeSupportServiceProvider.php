<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel;

use A1comms\GaeSupportLaravel\Filesystem\GaeAdapter as GaeFilesystemAdapter;
use A1comms\GaeSupportLaravel\Session\DatastoreSessionHandler;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem as Flysystem;

/**
 * Class GaeSupportServiceProvider.
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
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/gaesupport.php',
            'gaesupport'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
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
        Session::extend('gae', fn ($app) => new DatastoreSessionHandler());

        Storage::extend('gae', fn ($app, $config) => new Flysystem(new GaeFilesystemAdapter($config['root'])));

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
        return ['gae-support'];
    }
}
