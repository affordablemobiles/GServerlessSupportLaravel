<?php

namespace A1comms\GaeSupportLaravel\Foundation;

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
        $providers = Collection::make($this->app->config['app.providers'])
                        ->partition(function ($provider) {
                            return Str::startsWith($provider, 'Illuminate\\');
                        });

        $providers->splice(1, 0, [$this->app->make(PackageManifest::class)->providers()]);

        $this->compileManifest($providers);
    }
}