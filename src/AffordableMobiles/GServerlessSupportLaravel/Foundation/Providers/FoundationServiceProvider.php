<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Foundation\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Exceptions\Renderer\Listener;
use Illuminate\Foundation\Exceptions\Renderer\Mappers\BladeMapper;
use Illuminate\Foundation\Exceptions\Renderer\Renderer;
use Illuminate\Foundation\Providers\FoundationServiceProvider as LaravelFoundationServiceProvider;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;

class FoundationServiceProvider extends LaravelFoundationServiceProvider
{
    /**
     * Register the exceptions renderer.
     */
    protected function registerExceptionRenderer(): void
    {
        // Load views even if not in debug mode, to enable view pre-compiler.
        $this->loadViewsFrom(__DIR__.'/../../../../../../../laravel/framework/src/Illuminate/Foundation/resources/exceptions/renderer', 'laravel-exceptions-renderer');

        if (!$this->app->hasDebugModeEnabled()) {
            return;
        }

        $this->app->singleton(Renderer::class, static function (Application $app) {
            $errorRenderer = new HtmlErrorRenderer(
                $app['config']->get('app.debug'),
            );

            return new Renderer(
                $app->make(Factory::class),
                $app->make(Listener::class),
                $errorRenderer,
                $app->make(BladeMapper::class),
                $app->basePath(),
            );
        });

        $this->app->singleton(Listener::class);
    }
}
