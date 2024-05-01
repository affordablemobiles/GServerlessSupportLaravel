<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Log;

use Illuminate\Support\ServiceProvider;

class LogServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton('log', static fn ($app) => new LogManager($app));
    }
}
