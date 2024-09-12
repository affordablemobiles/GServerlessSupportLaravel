<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Foundation;

use Illuminate\Foundation\PackageManifest;
use Illuminate\Foundation\ProviderRepository as LaravelProviderRepository;
use Illuminate\Support\Collection;

class ProviderRepository extends LaravelProviderRepository
{
    /**
     * Create a new service repository instance.
     */
    public function __construct()
    {
        parent::__construct(app(), app('files'), app()->getCachedServicesPath());
    }

    public function preCompileManifest(): void
    {
        $providers = Collection::make($this->app->config['app.providers'])
            ->partition(static fn ($provider) => str_starts_with($provider, 'Illuminate\\'))
        ;

        $providers[0]->transform(static fn ($item) => FoundationServiceProvider::class === $item ? Providers\FoundationServiceProvider::class : $item);

        $providers->splice(1, 0, [$this->app->make(PackageManifest::class)->providers()]);

        $this->compileManifest($providers->collapse()->toArray());
    }
}
