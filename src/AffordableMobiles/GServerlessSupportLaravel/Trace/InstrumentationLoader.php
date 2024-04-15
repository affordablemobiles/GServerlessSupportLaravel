<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Trace;

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
        ];
    }
}
