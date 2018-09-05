<?php

namespace A1comms\GaeSupportLaravel\Trace;

/**
 * Class to return the low level trace modules to load.
 */
class LowLevelLoader implements LowLevelLoaderInterface
{
    /**
     * Static method to get the list of trace modules to load.

     */
    public static function getList()
    {
        return [
            // OpenCensus provides a basic Laravel trace adapter,
            // which covered Eloquent and view compilation.
            OpenCensus\Trace\Integrations\Laravel::class,
            // Also load our own extended Laravel trace set.
            A1comms\GaeSupportLaravel\Trace\Integration\LowLevel\LaravelExtended::class,
            // Trace our other basic functions...
            OpenCensus\Trace\Integrations\Mysql::class,
            OpenCensus\Trace\Integrations\PDO::class,
            OpenCensus\Trace\Integrations\Memcached::class,
        ];
    }
}