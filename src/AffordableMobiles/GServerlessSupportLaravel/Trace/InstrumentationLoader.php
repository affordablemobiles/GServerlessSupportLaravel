<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Trace;

use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\Guzzle\GuzzleInstrumentation;
use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\Laravel\LaravelBootInstrumentation;
use AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\Laravel\LaravelInstrumentation;

/**
 * Class to return the low level trace modules to load.
 */
class InstrumentationLoader implements InstrumentationLoaderInterface
{
    /**
     * Static method to get the list of trace modules to load.
     */
    public static function getInstrumentation()
    {
        return [
            LaravelBootInstrumentation::class,

            LaravelInstrumentation::class,

            GuzzleInstrumentation::class,
        ];
    }
}
