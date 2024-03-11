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
}
