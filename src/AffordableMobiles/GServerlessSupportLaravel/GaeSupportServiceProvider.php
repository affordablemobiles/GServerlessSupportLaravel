<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel;

use AffordableMobiles\GServerlessSupportLaravel\Filesystem\GServerlessAdapter as GServerlessFilesystemAdapter;
use AffordableMobiles\GServerlessSupportLaravel\Session\DatastoreSessionHandler;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem as Flysystem;

/**
 * Class GServerlessSupportServiceProvider.
 */
class GServerlessSupportServiceProvider extends ServiceProvider
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
            __DIR__.'/../../config/gserverlesssupport.php',
            'gserverlesssupport'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish our config file when the user runs "artisan vendor:publish".
        $this->publishes([
            __DIR__.'/../../config/gserverlesssupport.php' => config_path('gserverlesssupport.php'),
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\GServerlessPrepareCommand::class,
            ]);
        }

        // Register the DatastoreSessionHandler
        Session::extend('datastore', static fn ($app) => new DatastoreSessionHandler(
            config('session.table'),
            config('session.store'),
        ));

        Storage::extend('gserverless', static function ($app, $config) {
            $adapter = new GServerlessFilesystemAdapter($config['root']);

            return new FilesystemAdapter(
                new Flysystem($adapter, $config),
                $adapter,
                $config,
            );
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['g-serverless-support'];
    }
}
