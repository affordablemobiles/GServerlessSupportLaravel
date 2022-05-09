<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Database\Connectors;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Connectors\ConnectionFactory as LaravelConnectionFactory;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use PDOException;

class ConnectionFactory extends LaravelConnectionFactory
{
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
            foreach (Arr::shuffle($sockets = $this->parseSockets($config)) as $key => $socket) {
                $config['unix_socket'] = $socket;

                \Log::info('Connecting to DB unix_socket: '.$socket);

                try {
                    return $this->createConnector($config)->connect($config);
                } catch (PDOException $e) {
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
            throw new InvalidArgumentException('Database unix_socket array is empty.');
        }

        return $sockets;
    }
}
