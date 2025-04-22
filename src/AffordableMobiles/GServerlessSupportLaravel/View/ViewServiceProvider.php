<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\View;

use AffordableMobiles\GServerlessSupportLaravel\Console\GServerlessViewCompileCommand;
use AffordableMobiles\GServerlessSupportLaravel\View\Compilers\CompileTimeBladeCompilerWrapper;
use AffordableMobiles\GServerlessSupportLaravel\View\Compilers\FakeCompiler;
use AffordableMobiles\GServerlessSupportLaravel\View\Engines\CompilerEngine;
use Illuminate\Contracts\View\Factory as ViewFactoryContract;
use Illuminate\View\Compilers\BladeCompiler as LaravelBladeCompiler;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\ViewServiceProvider as LaravelViewServiceProvider;

/**
 * Provides view services, conditionally replacing components for serverless environments.
 */
class ViewServiceProvider extends LaravelViewServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $isServerless = \function_exists('is_g_serverless') && is_g_serverless();
        $isProduction = $this->app->environment('production');

        if ($isServerless && $isProduction) {
            $this->registerGServerlessViewFinder();
            $this->registerGServerlessEngineResolver();
            $this->registerGServerlessViewFactory(); // Register our custom factory
        } elseif ($isServerless && !$isProduction) {
            $writablePath = \function_exists('g_serverless_storage_path') ? realpath(g_serverless_storage_path('framework/views')) : null;
            if ($writablePath && is_dir($writablePath) && is_writable($writablePath)) {
                $this->app['config']->set('view.compiled', $writablePath);
            } else {
                report(new \RuntimeException('Could not resolve or write to serverless storage path for non-production view compilation. Path: '.($writablePath ?: 'N/A')));
            }
            parent::register();
            $this->registerBladeCompilerIfNotRegistered();
        } else {
            parent::register();
            $this->registerBladeCompilerIfNotRegistered();
            $this->registerViewCompilerCommand();
        }
    }

    /**
     * Register the GServerless view finder implementation (Runtime).
     */
    public function registerGServerlessViewFinder(): void
    {
        $this->app->singleton('view.finder', static fn ($app) => new FileViewFinder(
            $app['files'],
            $app['config']['view.paths'], // Keep using config for default paths
            null,
            $app['config']['view.compiled'] // Keep using config for cache path
        ));
    }

    /**
     * Register the GServerless engine resolver instance (Runtime).
     */
    public function registerGServerlessEngineResolver(): void
    {
        $this->app->singleton('view.engine.resolver', function ($app) {
            $resolver = new EngineResolver();
            foreach (['file', 'php'] as $engine) {
                if (method_exists($this, 'register'.ucfirst($engine).'Engine')) {
                    $this->{'register'.ucfirst($engine).'Engine'}($resolver);
                }
            }
            $this->registerGServerlessBladeEngine($resolver);

            return $resolver;
        });
    }

    /**
     * Register the GServerless Blade engine implementation (Runtime).
     *
     * @param mixed $resolver
     */
    public function registerGServerlessBladeEngine($resolver): void
    {
        $this->app->singleton('gserverless.blade.compiler.fake', static function ($app) {
            $finder           = $app['view.finder'];
            $runtimeCachePath = $app['config']['view.compiled'];
            if (empty($runtimeCachePath)) {
                throw new \RuntimeException('Runtime view cache path (config view.compiled) is not configured.');
            }
            // Pass only the 'views' part of the manifest to FakeCompiler
            if ($finder instanceof FileViewFinder) {
                return new FakeCompiler($finder->getManifestViews(), $runtimeCachePath); // Use getManifestViews()
            }

            throw new \RuntimeException('GServerless FileViewFinder not correctly registered or failed to load manifest.');
        });

        $resolver->register('blade', fn () => new CompilerEngine($this->app['gserverless.blade.compiler.fake']));
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        $isProductionServerless = \function_exists('is_g_serverless') && is_g_serverless() && $this->app->environment('production');
        $provides               = parent::provides();

        if ($isProductionServerless) {
            $provides = array_merge($provides, [
                'view.finder', 'view.engine.resolver', 'gserverless.blade.compiler.fake', 'view',
            ]);
            $provides = array_diff($provides, ['blade.compiler']);
        } else {
            $provides = array_merge($provides, [
                CompileTimeBladeCompilerWrapper::class, GServerlessViewCompileCommand::class, 'blade.compiler',
            ]);
        }

        return array_unique($provides);
    }

    /**
     * Register the custom GServerless View Factory (Runtime).
     */
    protected function registerGServerlessViewFactory(): void
    {
        // Ensure our finder is registered first
        if (!$this->app->bound('view.finder')) {
            $this->registerGServerlessViewFinder();
        }

        $this->app->singleton('view', static function ($app) {
            $resolver = $app['view.engine.resolver'];
            // Ensure we resolve our specific finder implementation
            $finder = $app['view.finder'];
            if (!$finder instanceof FileViewFinder) {
                // This might happen if another provider overrides 'view.finder' after us.
                throw new \RuntimeException('Incorrect ViewFinder implementation bound. Expected '.FileViewFinder::class);
            }
            $events    = $app['events'];
            $container = $app;

            // Instantiate our custom factory, passing the specific finder type
            $factory = new GServerlessViewFactory($resolver, $finder, $events, $container);

            $factory->setContainer($container);
            $factory->share('app', $container);

            return $factory;
        });
    }

    /**
     * Register the view compile command and its dependencies.
     */
    protected function registerViewCompilerCommand(): void
    {
        $this->app->singleton(CompileTimeBladeCompilerWrapper::class, static function ($app) {
            if (!$app->bound('blade.compiler')) {
                throw new \RuntimeException('Real Blade Compiler (blade.compiler) is not registered.');
            }

            return new CompileTimeBladeCompilerWrapper(
                $app['blade.compiler'],
                $app['files']
            );
        });

        $this->app->singleton(GServerlessViewCompileCommand::class, static fn ($app) => new GServerlessViewCompileCommand(
            $app['files'],
            $app[CompileTimeBladeCompilerWrapper::class],
            $app[ViewFactoryContract::class] // Inject factory contract
        ));

        $this->commands([GServerlessViewCompileCommand::class]);
    }

    /**
     * Ensures the standard Laravel Blade compiler is registered if it isn't already.
     */
    protected function registerBladeCompilerIfNotRegistered(): void
    {
        if (!$this->app->bound('blade.compiler')) {
            $this->app->singleton('blade.compiler', static fn ($app) => new LaravelBladeCompiler(
                $app['files'],
                $app['config']['view.compiled']
            ));
        }
    }
}
