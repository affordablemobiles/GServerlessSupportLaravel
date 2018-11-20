<?php

namespace A1comms\GaeSupportLaravel\Foundation;

use Laravel\Lumen\Application as LumenApp;

class LumenApplication extends LumenApp
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