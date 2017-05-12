<?php

namespace A1comms\GaeSupportLaravel\Storage;

use Memcached;

/**
 * class CacheFsMemcacheContainer
 *
 * Provides a wrapper around memcached as a mechanism to keep files in memory.
 *
 * @package A1comms\GaeSupportLaravel\Storage
 */
final class CacheFsMemcacheContainer extends Memcached
{
    /**
    * $local_cache - The variable to store a thread-local cache.
    *
    * @var array
    *
    * @access private
    */
    private $local_cache = [];


    public function get($key, $cache_cb = null, &$cas_token = null)
    {
        if ( isset($this->local_cache[$key]) ){
            return $this->local_cache[$key];
        }

        $value = parent::get($key, $cache_cb, $cas_token);

        if ($value !== false)
        {
            $this->local_cache[$key] = $value;
        }

        return $value;
    }

    public function set($key, $value, $expiration = null)
    {
        $this->local_cache[$key] = $value;

        return parent::set($key, $value, $expiration);
    }

    public function delete($key, $time = null)
    {
        unset($this->local_cache[$key]);

        return parent::delete($key, $time);
    }
}
