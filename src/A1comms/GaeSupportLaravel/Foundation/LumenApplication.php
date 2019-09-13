<?php

namespace A1comms\GaeSupportLaravel\Foundation;

use Laravel\Lumen\Application as BaseLumenApplication;
use A1comms\GaeSupportLaravel\Log\Logger;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem as Flysystem;
use Google\Cloud\Storage\StorageClient as GCSStorageClient;
use Google\Cloud\Storage\StreamWrapper as GCSStreamWrapper;
use A1comms\GaeSupportLaravel\Filesystem\GaeAdapter as GaeFilesystemAdapter;

class LumenApplication extends BaseLumenApplication
{
    /**
     * Create a new Illuminate application instance.
     *
     * @param  string|null  $basePath
     * @return void
     */
    public function __construct($basePath = null)
    {
        $return = parent::__construct($basePath);

        Logger::setup($this);

        $this->withFacades();

        if (class_exists('League\Flysystem\Filesystem')) {
            Storage::extend('gae', function ($app, $config) {
                return new Flysystem(new GaeFilesystemAdapter($config['root']));
            });
        }

        if (is_gae()) {
            $storage = new GCSStorageClient();
            GCSStreamWrapper::register($storage);
        }

        return $return;
    }
    
    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerViewBindings()
    {
        $this->configure('view');
    }
}
