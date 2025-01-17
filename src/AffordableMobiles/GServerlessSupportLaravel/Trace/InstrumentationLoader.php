<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Trace;

use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\Datastore\Eloquent\EloquentDatastoreInstrumentation;
use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\Guzzle\GuzzleInstrumentation;
use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\Laravel\LaravelBootInstrumentation;
use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\Laravel\LaravelInstrumentation;

/**
 * Class to return the trace instrumentation to load.
 */
class InstrumentationLoader implements InstrumentationLoaderInterface
{
    /**
     * Static method to get the list of trace instrumentation to load.
     */
    public static function getInstrumentation()
    {
        return [
            LaravelBootInstrumentation::class,

            LaravelInstrumentation::class,

            GuzzleInstrumentation::class,

            EloquentDatastoreInstrumentation::class,
        ];
    }
}
