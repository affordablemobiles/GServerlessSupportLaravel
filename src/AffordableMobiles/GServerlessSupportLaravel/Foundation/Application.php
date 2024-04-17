<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Foundation;

use Illuminate\Foundation\Application as LaravelApplication;

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
     * @param  string|null  $basePath
     * @return ApplicationBuilder
     */
    public static function configure(?string $basePath = null)
    {
        $basePath = match (true) {
            is_string($basePath) => $basePath,
            default => static::inferBasePath(),
        };

        return (new ApplicationBuilder(new static($basePath)))
            ->withKernels()
            ->withEvents()
            ->withCommands()
            ->withProviders();
    }
}
