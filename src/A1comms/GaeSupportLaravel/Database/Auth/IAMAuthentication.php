<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Database\Auth;

use Google\Auth\Credentials\GCECredentials;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Support\Str;

class IAMAuthentication
{
    private $cache;
    private $credentials;

    public function __construct()
    {
        // Register our own cache, as the default isn't started at the time DB config is loaded.
        $this->cache = new CacheRepository(
            new ArrayStore(),
        );
        $this->credentials = new GCECredentials();
    }

    public function username()
    {
        return $this->cache->rememberForever(
            __CLASS__.'__'.__METHOD__,
            fn () => Str::before($this->credentials->getClientName(), '@')
        );
    }

    public function password()
    {
        return $this->cache->remember(
            __CLASS__.'__'.__METHOD__,
            fn ($result) => (new \DateTime())->setTimestamp($result['expires_at'] ?? 0),
            fn ()        => $this->credentials->fetchAuthToken()
        )['access_token'];
    }
}
