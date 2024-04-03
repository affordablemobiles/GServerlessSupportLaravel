<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\View;

use AffordableMobiles\GServerlessSupportLaravel\Console;
use AffordableMobiles\GServerlessSupportLaravel\View\Compilers\FakeCompiler;
use AffordableMobiles\GServerlessSupportLaravel\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\ViewServiceProvider as LaravelViewServiceProvider;

class ViewServiceProvider extends LaravelViewServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        if (is_g_serverless() && ('production' === env('APP_ENV', 'production'))) {
            $this->registerFactory();

            $this->registerGServerlessViewFinder();

            $this->registerGServerlessBladeCompiler();

            $this->registerGServerlessEngineResolver();
        } elseif (is_g_serverless()) {
            app()->config['view.compiled'] = realpath(g_serverless_storage_path('framework/views'));
            parent::register();
        } else {
            parent::register();
        }
    }

    /**
     * Register the console commands.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\GServerlessViewCompileCommand::class,
            ]);
        }
    }

    /**
     * Register the view finder implementation.
     */
    public function registerGServerlessViewFinder(): void
    {
        $this->app->bind('view.finder', function ($app) {
            // TODO: Replace with a static manifest array search.
            return new FileViewFinder($app['files'], $app['config']['view.paths'], null, $this->app['config']['view.compiled']);
        });
    }

    /**
     * Register the Blade compiler implementation.
     */
    public function registerGServerlessBladeCompiler(): void
    {
        // The Compiler engine requires an instance of the CompilerInterface, which in
        // this case will be the Blade compiler, so we'll first create the compiler
        // instance to pass into the engine so it can compile the views properly.
        $this->app->singleton('blade.compiler', function () {
            return new FakeCompiler(
                $this->app['config']['view.compiled']
            );
        });
    }

    /**
     * Register the engine resolver instance.
     */
    public function registerGServerlessEngineResolver(): void
    {
        $this->app->singleton('view.engine.resolver', function () {
            $resolver = new EngineResolver();

            // Next, we will register the various view engines with the resolver so that the
            // environment will resolve the engines needed for various views based on the
            // extension of view file. We call a method for each of the view's engines.
            foreach (['file', 'php'] as $engine) {
                $this->{'register'.ucfirst($engine).'Engine'}($resolver);
            }

            foreach (['blade'] as $engine) {
                $this->{'registerGServerless'.ucfirst($engine).'Engine'}($resolver);
            }

            return $resolver;
        });
    }

    /**
     * Register the Blade engine implementation.
     *
     * @param EngineResolver $resolver
     */
    public function registerGServerlessBladeEngine($resolver): void
    {
        $resolver->register('blade', fn () => new CompilerEngine($this->app['blade.compiler']));
    }
}
