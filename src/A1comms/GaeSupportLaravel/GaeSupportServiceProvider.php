<?php

namespace A1comms\GaeSupportLaravel;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Session;
use League\Flysystem\Filesystem as Flysystem;
use Google\Cloud\Storage\StorageClient as GCSStorageClient;
use Google\Cloud\Storage\StreamWrapper as GCSStreamWrapper;
use A1comms\GaeSupportLaravel\Artisan\SetupCommand;
use A1comms\GaeSupportLaravel\Artisan\PrepareCommand;
use A1comms\GaeSupportLaravel\Filesystem\GaeAdapter as GaeFilesystemAdapter;
use A1comms\GaeSupportLaravel\Session\DataStoreSessionHandler;

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
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Things to initialize only if we are running on GAE Flex.
        if (is_gae_flex()) {
            // Register the gs:// stream wrapper, as it isn't automatic on Flex.
            $storage = new GCSStorageClient();
            GCSStreamWrapper::register($storage);
        }

        Session::extend('gae', function($app) {
            // Return implementation of SessionHandlerInterface...
            return new DataStoreSessionHandler;
        });

        Storage::extend('gae', function ($app, $config) {
            return new Flysystem(new GaeFilesystemAdapter($config['root']));
        });
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app['gae.setup'] = $this->app->share(function ($app) {
            return new SetupCommand;
        });
        $this->app['gae.prepare'] = $this->app->share(function ($app) {
            return new PrepareCommand;
        });
        $this->commands([
            'gae.setup',
            'gae.prepare',
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
