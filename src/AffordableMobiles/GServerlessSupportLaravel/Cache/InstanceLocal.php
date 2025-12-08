<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Cache;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * @deprecated This class is maintained for backwards compatibility.
 * Please use Cache::store('instance-scoped') instead.
 */
class InstanceLocal extends CacheManager
{
    /**
     * The file path for the instance-local cache.
     * Kept here for visibility and easy developer discovery.
     */
    public const CACHE_PATH = '/tmp/cache/GServerlessSupportLaravel';

    private static $instance;

    /**
     * Dynamically call the default driver instance (statically).
     * This supports calls like InstanceLocal::get(...).
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        // This will call ->store() on the instance, which hits our override above
        return self::getInstance()->store()->{$method}(...$parameters);
    }

    /**
     * Get a singleton instance of this class.
     * We keep this to support calls like InstanceLocal::getInstance()->get(...).
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self(app());
        }

        return self::$instance;
    }

    /**
     * Override the store method.
     * * Regardless of what store name is requested (or if null is passed),
     * we intercept the call and return the repository for our specific
     * 'instance-scoped' store configuration.
     *
     * @param null|string $name
     *
     * @return Repository
     */
    public function store($name = null)
    {
        return Cache::store('instance-scoped');
    }
}
