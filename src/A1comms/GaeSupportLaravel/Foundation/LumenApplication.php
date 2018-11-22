<?php

namespace A1comms\GaeSupportLaravel\Foundation;

use Laravel\Lumen\Application as LumenApp;
use A1comms\GaeSupportLaravel\Log\Logger;

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

    /**
     * Get the Monolog handler for the application.
     *
     * @return \Monolog\Handler\AbstractHandler
     */
    protected function getMonologHandler()
    {
        $handler = Logger::getHandler();

        if (is_null($handler)) {
            return parent::getMonologHandler();
        } else {
            return $handler;
        }
    }
}