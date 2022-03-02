<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Database;

use A1comms\GaeSupportLaravel\Database\Connectors\ConnectionFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\DatabaseServiceProvider as LaravelDatabaseServiceProvider;

class DatabaseServiceProvider extends LaravelDatabaseServiceProvider
{
    /**
     * Register the primary database bindings.
     */
    protected function registerConnectionServices(): void
    {
        // The connection factory is used to create the actual connection instances on
        // the database. We will inject the factory into the manager so that it may
        // make the connections while they are actually needed and not of before.
        $this->app->singleton('db.factory', fn ($app) => new ConnectionFactory($app));

        // The database manager is used to resolve various connections, since multiple
        // connections might be managed. It also implements the connection resolver
        // interface which may be used by other components requiring connections.
        $this->app->singleton('db', fn ($app) => new DatabaseManager($app, $app['db.factory']));

        $this->app->bind('db.connection', fn ($app) => $app['db']->connection());
    }
}
