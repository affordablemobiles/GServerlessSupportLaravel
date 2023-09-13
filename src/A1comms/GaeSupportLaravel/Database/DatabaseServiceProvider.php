<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Database;

use A1comms\GaeSupportLaravel\Database\Auth\IAMAuthentication;
use A1comms\GaeSupportLaravel\Database\Connectors\ConnectionFactory;
use Illuminate\Support\ServiceProvider;

class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     */
    public function boot(): void
    {
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        // The connection factory is used to create the actual connection instances on
        // the database. We will inject the factory into the manager so that it may
        // make the connections while they are actually needed and not of before.
        $this->app->singleton('db.factory', fn ($app) => new ConnectionFactory($app));

        // This authentication handler enables IAM authentication on GCP.
        $this->app->singleton(IAMAuthentication::class, fn () => new IAMAuthentication());
    }
}
