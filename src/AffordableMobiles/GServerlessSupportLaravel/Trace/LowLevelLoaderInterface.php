<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Trace;

/**
 * Interface to implement for loading your own list
 * of low level trace modules.
 */
interface LowLevelLoaderInterface
{
    /**
     * Static method to get the list of trace modules to load.
     *
     * For an example, see the default at LowLevelLoader.
     */
    public static function getList();
}
