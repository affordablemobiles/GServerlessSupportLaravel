<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel;

use AffordableMobiles\GServerlessSupportLaravel\Cache\InstanceLocal;
use AffordableMobiles\GServerlessSupportLaravel\Filesystem\GServerlessAdapter as GServerlessFilesystemAdapter;
use AffordableMobiles\GServerlessSupportLaravel\Session\DatastoreSessionHandler;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
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
        // Register the custom cache store configuration dynamically.
        //  We use the constant from InstanceLocal so developers can find the path easily.
        $this->app['config']->set('cache.stores.instance-scoped', [
            'driver' => 'file',
            'path'   => InstanceLocal::CACHE_PATH,
        ]);

        // Publish our config file when the user runs "artisan vendor:publish".
        $this->publishes([
            __DIR__.'/../../config/gserverlesssupport.php' => config_path('gserverlesssupport.php'),
        ]);

        $this->shareAssetPaths();

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\GServerlessPrepareCommand::class,
                Console\GServerlessPublishAssetsCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../../resources/js/dist' => public_path('vendor/g-serverless-support'),
            ], 'gss-js-assets');
        }

        // Register the DatastoreSessionHandler
        Session::extend('datastore', static fn ($app) => new DatastoreSessionHandler(
            config('session.table', 'sessions'),
            config('session.namespace', null),
            config('session.database', '')
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

    /**
     * Finds and shares the versioned asset paths from the Vite manifest.
     */
    private function shareAssetPaths(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        $manifestPath = public_path('vendor/g-serverless-support/.vite/manifest.json');

        if (!file_exists($manifestPath)) {
            return;
        }

        try {
            $manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

            $entryPoints = [
                'errorReporter' => 'src/resources/js/error-reporter.js',
            ];

            foreach ($entryPoints as $viewVariableName => $sourceFile) {
                if (isset($manifest[$sourceFile]['file'])) {
                    $assetPath = asset('vendor/g-serverless-support/'.$manifest[$sourceFile]['file']);

                    View::share($viewVariableName.'AssetPath', $assetPath);
                }
            }
        } catch (\JsonException $e) {
            // Manifest is likely corrupt, do nothing.
            return;
        }
    }
}
