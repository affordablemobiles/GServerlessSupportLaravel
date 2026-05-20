<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Database\MongoDB;

use MongoDB\Client;
use MongoDB\Laravel\Connection as BaseConnection;

class Connection extends BaseConnection
{
    /**
     * Create a new MongoDB connection.
     *
     * Overridden to resolve Closure values for username and password,
     * enabling dynamic IAM authentication credentials for
     * Firestore in MongoDB compatibility mode on GCP.
     */
    protected function createConnection(string $dsn, array $config, array $options): Client
    {
        if (\array_key_exists('username', $config)) {
            $config['username'] = value($config['username']);
        }

        if (\array_key_exists('password', $config)) {
            $config['password'] = value($config['password']);
        }

        return parent::createConnection($dsn, $config, $options);
    }
}
