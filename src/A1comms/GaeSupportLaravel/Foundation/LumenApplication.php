<?php

namespace A1comms\GaeSupportLaravel\Foundation;

use Laravel\Lumen\Application as LumenApplication;

class LumenApplication extends LumenApplication
{
    /**
     * Create a new Illuminate application instance.
     *
     * @param  string|null  $basePath
     * @return void
     */
    public function __construct($basePath = null)
    {
        return parent::__construct($basePath);
    }
}