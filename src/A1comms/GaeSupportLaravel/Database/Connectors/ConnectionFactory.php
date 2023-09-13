<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Database\Connectors;

use A1comms\GaeSupportLaravel\Cache\InstanceLocal as InstanceLocalCache;
use A1comms\sqlcommenter\Connectors\ConnectionFactory as BaseConnectionFactory;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Arr;

class ConnectionFactory extends BaseConnectionFactory
{
    /**
     * Parse and prepare the database configuration.
     *
     * @param string $name
     *
     * @return array
     */
    protected function parseConfig(array $config, $name)
    {
        $config = parent::parseConfig($config, $name);

        if (\array_key_exists('username', $config)) {
            $config['username'] = value($config['username']);
        }
        if (\array_key_exists('password', $config)) {
            $config['password'] = value($config['password']);
        }

        return $config;
    }

    /**
     * Create a new Closure that resolves to a PDO instance.
     *
     * @return \Closure
     */
    protected function createPdoResolver(array $config)
    {
        if (\array_key_exists('unix_socket', $config)) {
            return $this->createPdoResolverWithSockets($config);
        }
        if (\array_key_exists('host', $config)) {
            return $this->createPdoResolverWithHosts($config);
        }

        return $this->createPdoResolverWithoutHosts($config);
    }

    /**
     * Create a new Closure that resolves to a PDO instance with a specific socket or an array of sockets.
     *
     * @return \Closure
     */
    protected function createPdoResolverWithSockets(array $config)
    {
        return function () use ($config) {
            $cacheKey = 'GaeSupportLaravel-Database-ConnectionFactory-'.$config['name'];
            $sockets  = Arr::shuffle($this->parseSockets($config));

            $current = is_gae_development() ? null : InstanceLocalCache::get($cacheKey);
            if (!empty($current)) {
                $sockets = array_filter($sockets, fn ($socket) => $socket !== $current);
                array_unshift($sockets, $current);
            }

            foreach ($sockets as $key => $socket) {
                $config['unix_socket'] = $socket;

                \Log::info('Connecting to DB unix_socket: '.$socket);

                try {
                    $connection = $this->createConnector($config)->connect($config);

                    InstanceLocalCache::forever($cacheKey, $socket);

                    return $connection;
                } catch (\PDOException $e) {
                    if (\count($sockets) - 1 === $key && $this->container->bound(ExceptionHandler::class)) {
                        $this->container->make(ExceptionHandler::class)->report($e);
                    }
                }
            }

            throw $e;
        };
    }

    /**
     * Parse the hosts configuration item into an array.
     *
     * @return array
     */
    protected function parseSockets(array $config)
    {
        $sockets = Arr::wrap($config['unix_socket']);

        if (empty($sockets)) {
            throw new \InvalidArgumentException('Database unix_socket array is empty.');
        }

        return $sockets;
    }
}
