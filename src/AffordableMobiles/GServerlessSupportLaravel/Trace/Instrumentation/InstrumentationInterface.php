<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation;

use OpenTelemetry\API\Instrumentation\CachedInstrumentation;

/**
 * Interface to implement for instrumentation.
 */
interface InstrumentationInterface
{
    public static function register(CachedInstrumentation $instrumentation);
}
