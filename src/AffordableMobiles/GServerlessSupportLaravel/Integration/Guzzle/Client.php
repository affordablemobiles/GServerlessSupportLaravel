<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Integration\Guzzle;

use GuzzleHttp\Client as BaseClient;

class Client extends BaseClient
{
    public function __construct(array $config = [])
    {
        if (!isset($config['handler'])) {
            $config['handler'] = HandlerStack::create();
        }

        return parent::__construct($config);
    }
}
