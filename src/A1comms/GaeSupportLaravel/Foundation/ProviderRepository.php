<?php

namespace A1comms\GaeSupportLaravel\Foundation;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\ProviderRepository as LaravelProviderRepository;

class ProviderRepository extends LaravelProviderRepository
{
    /**
     * Create a new service repository instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct(app(), new Filesystem, app()->getCachedServicesPath());
    }

    /**
     * Pre-compile the manifest, so it doesn't happen in production.
     */
    public function preCompileManifest()
    {
        $this->compileManifest($this->app->config['app.providers']);
    }

    /**
     * Determine if the manifest should be compiled.
     *
     * @param  array  $manifest
     * @param  array  $providers
     * @return bool
     */
    public function shouldRecompile($manifest, $providers)
    {
        return false;
    }
}