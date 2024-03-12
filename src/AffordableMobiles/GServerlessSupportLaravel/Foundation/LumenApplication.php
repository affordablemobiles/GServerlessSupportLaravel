<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Foundation;

use AffordableMobiles\GServerlessSupportLaravel\Filesystem\GServerlessAdapter as GServerlessFilesystemAdapter;
use Google\Cloud\Storage\StorageClient as GCSStorageClient;
use Google\Cloud\Storage\StreamWrapper as GCSStreamWrapper;
use Illuminate\Support\Facades\Storage;
use Laravel\Lumen\Application as BaseLumenApplication;
use League\Flysystem\Filesystem as Flysystem;

class LumenApplication extends BaseLumenApplication
{
    /**
     * Create a new Illuminate application instance.
     *
     * @param null|string $basePath
     */
    public function __construct($basePath = null)
    {
        $return = parent::__construct($basePath);

        $this->mergeConfigFrom(
            __DIR__.'/../../../config/logging.php',
            'logging',
            true
        );

        $this->withFacades();

        if (class_exists('League\Flysystem\Filesystem')) {
            Storage::extend('gserverless', static fn ($app, $config) => new Flysystem(new GServerlessFilesystemAdapter($config['root'])));
        }

        if (is_g_serverless()) {
            $storage = new GCSStorageClient();
            GCSStreamWrapper::register($storage);
        }

        $this->mergeConfigFrom(
            __DIR__.'/../../../config/gserverlesssupport.php',
            'gserverlesssupport'
        );

        return $return;
    }

    /**
     * Merge the given configuration with the existing configuration.
     *
     * @param string $path
     * @param string $key
     * @param mixed  $flip
     */
    protected function mergeConfigFrom($path, $key, $flip = false): void
    {
        $this->configure($key);

        if ($flip) {
            $this['config']->set($key, array_merge(
                $this['config']->get($key, []),
                require $path
            ));
        } else {
            $this['config']->set($key, array_merge(
                require $path,
                $this['config']->get($key, [])
            ));
        }
    }

    /**
     * Register container bindings for the application.
     */
    protected function registerViewBindings(): void
    {
        if (!empty($this->loadedProviders['AffordableMobiles\GServerlessSupportLaravel\View\ViewServiceProvider'])) {
            $this->configure('view');
        } else {
            parent::registerViewBindings();
        }
    }
}
