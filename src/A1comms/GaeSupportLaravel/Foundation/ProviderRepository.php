<?php

namespace A1comms\GaeSupportLaravel\Foundation;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Foundation\PackageManifest;
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
        parent::__construct(app(), app('files'), app()->getCachedServicesPath());
    }

    public function preCompileManifest()
    {
        $this->compileManifest($this->app->config['app.providers']);
    }
}