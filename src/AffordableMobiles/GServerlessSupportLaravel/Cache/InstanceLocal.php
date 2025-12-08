<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Cache;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * @deprecated Use the 'instance-scoped' cache store instead.
 * Example: Cache::store('instance-scoped')->get('key');
 *
 * @mixin \Illuminate\Contracts\Cache\Repository
 */
class InstanceLocal extends CacheManager
{
    private static $instance;

    /**
     * Handle static calls like InstanceLocal::get().
     * Optimized to skip local instantiation.
     *
     * @param mixed $method
     * @param mixed $parameters
     */
    public static function __callStatic($method, $parameters)
    {
        return Cache::store('instance-scoped')->{$method}(...$parameters);
    }

    /**
     * Get a singleton instance of this class.
     * Keeps backward compatibility for code using InstanceLocal::getInstance()->get().
     *
     * @return static
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self(app());
        }

        return self::$instance;
    }

    /**
     * Override the store method to redirect instance calls.
     * This handles: $instance = new InstanceLocal(...); $instance->get(...);.
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
