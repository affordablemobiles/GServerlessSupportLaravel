<?php

namespace A1comms\GaeSupportLaravel\Cache;

use Illuminate\Cache\CacheManager;

class InstanceLocal extends CacheManager
{
    private static $instance;

    private $driver;
  
    /**
     * Get a singleton instance of this class.
     *
     * @return \Illuminate\Contracts\Cache\Repository
     */
    public static function getInstance() 
    {
        if (!self::$instance) {
            self::$instance = new self(app());    
        }
        
        return self::$instance;
    }

    /**
     * Get the driver instance.
     *
     * @return \Illuminate\Contracts\Cache\Repository
     */
    private function getDriver()
    {
        if (!$this->driver) {
            $this->driver = $this->createFileDriver(['path' => '/tmp/cache/GaeSupportLaravel']);
        }

        return $this->driver;
    }

    /**
     * Get a cache store instance by name.
     *
     * @param  string|null  $name
     * @return \Illuminate\Contracts\Cache\Repository
     */
    public function store($name = null)
    {
        return $this->getDriver();
    }

    /**
     * Dynamically call the default driver instance (statically).
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        return self::getInstance()->store()->$method(...$parameters);
    }
}