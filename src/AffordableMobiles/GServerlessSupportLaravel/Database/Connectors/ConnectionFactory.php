<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Database\Connectors;

use AffordableMobiles\GServerlessSupportLaravel\Cache\InstanceLocal as InstanceLocalCache;
use AffordableMobiles\sqlcommenter\Connectors\ConnectionFactory as BaseConnectionFactory;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Arr;

class ConnectionFactory extends BaseConnectionFactory
{
    protected function resolveRuntimeConfig(array $config)
    {
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
            $cacheKey = 'GServerlessSupportLaravel-Database-ConnectionFactory-'.$config['name'];
            $sockets  = Arr::shuffle($this->parseSockets($config));

            $current = is_g_serverless_development() ? null : InstanceLocalCache::get($cacheKey);
            if (!empty($current)) {
                $sockets = array_filter($sockets, static fn ($socket) => $socket !== $current);
                array_unshift($sockets, $current);
            }

            foreach ($sockets as $key => $socket) {
                $config['unix_socket'] = $socket;

                \Log::info('Connecting to DB unix_socket: '.$socket);

                try {
                    $runtimeConfig = $this->resolveRuntimeConfig($config);
                    $connection    = $this->createConnector($runtimeConfig)->connect($runtimeConfig);

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

    /**
     * Create a new Closure that resolves to a PDO instance with a specific host or an array of hosts.
     *
     * @return \Closure
     *
     * @throws \PDOException
     */
    protected function createPdoResolverWithHosts(array $config)
    {
        return function () use ($config) {
            foreach (Arr::shuffle($this->parseHosts($config)) as $host) {
                $config['host'] = $host;

                try {
                    $runtimeConfig = $this->resolveRuntimeConfig($config);

                    return $this->createConnector($runtimeConfig)->connect($runtimeConfig);
                } catch (PDOException $e) {
                    continue;
                }
            }

            throw $e;
        };
    }

    /**
     * Create a new Closure that resolves to a PDO instance where there is no configured host.
     *
     * @return \Closure
     */
    protected function createPdoResolverWithoutHosts(array $config)
    {
        return function () use ($config) {
            $runtimeConfig = $this->resolveRuntimeConfig($config);

            return $this->createConnector($runtimeConfig)->connect($runtimeConfig);
        };
    }
}
