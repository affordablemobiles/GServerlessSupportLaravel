<?php

namespace A1comms\GaeSupportLaravel\View;

use Illuminate\View\ViewServiceProvider as LaravelViewServiceProvider;
use Illuminate\View\Engines\EngineResolver;
use A1comms\GaeSupportLaravel\View\Compilers\FakeCompiler;
use A1comms\GaeSupportLaravel\View\Engines\CompilerEngine;
use A1comms\GaeSupportLaravel\Console;

class ViewServiceProvider extends LaravelViewServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        if (is_gae() && (env('APP_ENV', 'production') == 'production')) {
            $this->registerFactory();

            $this->registerGaeViewFinder();

            $this->registerGaeBladeCompiler();

            $this->registerGaeEngineResolver();
        } else {
            parent::register();
        }
    }

    /**
     * Register the console commands.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\GaeViewCompileCommand::class,
            ]);
        }
    }

    /**
     * Register the view finder implementation.
     *
     * @return void
     */
    public function registerGaeViewFinder()
    {
        $this->app->bind('view.finder', function ($app) {
            // TODO: Replace with a static manifest array search.
            return new FileViewFinder($app['files'], $app['config']['view.paths'], null, $this->app['config']['view.compiled']);
        });
    }

    /**
     * Register the Blade compiler implementation.
     *
     * @return void
     */
    public function registerBladeCompiler()
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
     *
     * @return void
     */
    public function registerGaeEngineResolver()
    {
        $this->app->singleton('view.engine.resolver', function () {
            $resolver = new EngineResolver;

            // Next, we will register the various view engines with the resolver so that the
            // environment will resolve the engines needed for various views based on the
            // extension of view file. We call a method for each of the view's engines.
            foreach (['file', 'php'] as $engine) {
                $this->{'register'.ucfirst($engine).'Engine'}($resolver);
            }

            foreach (['blade'] as $engine) {
                $this->{'registerGae'.ucfirst($engine).'Engine'}($resolver);
            }

            return $resolver;
        });
    }

    /**
     * Register the Blade engine implementation.
     *
     * @param  \Illuminate\View\Engines\EngineResolver  $resolver
     * @return void
     */
    public function registerGaeBladeEngine($resolver)
    {
        $resolver->register('blade', function () {
            return new CompilerEngine($this->app['blade.compiler']);
        });
    }
}
