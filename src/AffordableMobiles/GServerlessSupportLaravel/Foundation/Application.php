<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Foundation;

use AffordableMobiles\GServerlessSupportLaravel\Log\LogServiceProvider;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Log\Context\ContextServiceProvider;
use Illuminate\Routing\RoutingServiceProvider;

class Application extends LaravelApplication
{
    /**
     * Create a new Illuminate application instance.
     *
     * @param null|string $basePath
     */
    public function __construct($basePath = null)
    {
        return parent::__construct($basePath);
    }

    /**
     * Begin configuring a new Laravel application instance.
     *
     * @return Configuration\ApplicationBuilder
     */
    public static function configure(?string $basePath = null)
    {
        $basePath = match (true) {
            \is_string($basePath) => $basePath,
            default               => static::inferBasePath(),
        };

        return (new Configuration\ApplicationBuilder(new static($basePath)))
            ->withKernels()
            ->withEvents()
            ->withCommands()
            ->withProviders()
        ;
    }

    /**
     * Register all of the base service providers.
     */
    protected function registerBaseServiceProviders(): void
    {
        $this->register(new EventServiceProvider($this));
        $this->register(new LogServiceProvider($this));
        $this->register(new ContextServiceProvider($this));
        $this->register(new RoutingServiceProvider($this));
    }
}
