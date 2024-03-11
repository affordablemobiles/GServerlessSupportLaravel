<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Trace;

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
            \OpenCensus\Trace\Integrations\Laravel::class,
            // Also load our own extended Laravel trace set.
            \AffordableMobiles\GServerlessSupportLaravel\Trace\Integration\LowLevel\LaravelExtended::class,
            // Authentication Guards...
            \AffordableMobiles\GServerlessSupportLaravel\Trace\Integration\LowLevel\LaravelAuth::class,
            // Trace our other basic functions...
            \OpenCensus\Trace\Integrations\Mysql::class,
            \OpenCensus\Trace\Integrations\PDO::class,
            \OpenCensus\Trace\Integrations\Memcached::class,
            \AffordableMobiles\GServerlessSupportLaravel\Trace\Integration\LowLevel\Grpc::class,
            // Plus GDS (Datastore)...
            \AffordableMobiles\GServerlessSupportLaravel\Trace\Integration\LowLevel\GDS::class,
            // Guzzle calls...
            \AffordableMobiles\GServerlessSupportLaravel\Trace\Integration\Guzzle\TraceProvider::class,
        ];
    }
}
