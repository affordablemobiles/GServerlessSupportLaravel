<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Cache;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Repository;

class InstanceLocal extends CacheManager
{
    private static $instance;

    private $driver;

    /**
     * Dynamically call the default driver instance (statically).
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        return self::getInstance()->store()->{$method}(...$parameters);
    }

    /**
     * Get a singleton instance of this class.
     *
     * @return Repository
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self(app());
        }

        return self::$instance;
    }

    /**
     * Get a cache store instance by name.
     *
     * @param null|string $name
     *
     * @return Repository
     */
    public function store($name = null)
    {
        return $this->getDriver();
    }

    /**
     * Get the driver instance.
     *
     * @return Repository
     */
    private function getDriver()
    {
        if (!$this->driver) {
            $this->driver = $this->createFileDriver(['path' => '/tmp/cache/GServerlessSupportLaravel']);
        }

        return $this->driver;
    }
}
