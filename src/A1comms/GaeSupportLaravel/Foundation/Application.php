<?php

namespace A1comms\GaeSupportLaravel\Foundation;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Foundation\ProviderRepository as LaravelProviderRepository;
use A1comms\GaeSupportLaravel\Log\Logger;

class Application extends LaravelApplication
{
    /**
     * Create a new Illuminate application instance.
     *
     * @param  string|null  $basePath
     * @return void
     */
    public function __construct($basePath = null)
    {
        $handler = Logger::getHandler();

        if (!is_null($handler)) {
            $this->configureMonologUsing(function ($monolog) use ($handler) {
                $monolog->pushHandler($handler);
            });
        }

        return parent::__construct($basePath);
    }

    /**
     * Register all of the configured providers.
     *
     * @return void
     */
    public function registerConfiguredProviders()
    {
        if (is_gae()) {
            (new ProviderRepository())
                        ->load($this->config['app.providers']);
        } else {
            $manifestPath = $this->getCachedServicesPath();
            (new LaravelProviderRepository($this, new Filesystem, $manifestPath))
                        ->load($this->config['app.providers']);
        }
    }
}