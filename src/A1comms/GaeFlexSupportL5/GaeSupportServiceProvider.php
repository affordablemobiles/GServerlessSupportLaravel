<?php

namespace A1comms\GaeFlexSupportL5;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Session;
use League\Flysystem\Filesystem as Flysystem;
use Google\Cloud\Storage\StorageClient as GCSStorageClient;
use Google\Cloud\Storage\StreamWrapper as GCSStreamWrapper;
use A1comms\GaeFlexSupportL5\Setup\SetupCommand;
use A1comms\GaeFlexSupportL5\Filesystem\GaeAdapter as GaeFilesystemAdapter;
use A1comms\GaeFlexSupportL5\Session\DataStoreSessionHandler;

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
        if (!empty(gae_instance())) {
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
        $this->commands('gae.setup');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array('gae-flex-support');
    }
}
