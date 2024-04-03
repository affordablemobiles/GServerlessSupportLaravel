<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Trace;

use AffordableMobiles\GServerlessSupportLaravel\Trace\Integration\Guzzle\TraceProvider;
use AffordableMobiles\GServerlessSupportLaravel\Trace\Integration\LowLevel\GDS;
use AffordableMobiles\GServerlessSupportLaravel\Trace\Integration\LowLevel\Grpc;
use AffordableMobiles\GServerlessSupportLaravel\Trace\Integration\LowLevel\LaravelAuth;
use AffordableMobiles\GServerlessSupportLaravel\Trace\Integration\LowLevel\LaravelExtended;
use OpenCensus\Trace\Integrations\Laravel;
use OpenCensus\Trace\Integrations\Memcached;
use OpenCensus\Trace\Integrations\Mysql;
use OpenCensus\Trace\Integrations\PDO;

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
            Laravel::class,
            // Also load our own extended Laravel trace set.
            LaravelExtended::class,
            // Authentication Guards...
            LaravelAuth::class,
            // Trace our other basic functions...
            Mysql::class,
            PDO::class,
            Memcached::class,
            Grpc::class,
            // Plus GDS (Datastore)...
            GDS::class,
            // Guzzle calls...
            TraceProvider::class,
        ];
    }
}
