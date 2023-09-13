<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Database\Auth;

use Google\Auth\Credentials\GCECredentials;
use Illuminate\Support\Str;

class IAMAuthentication
{
    private $credentials;

    public function __construct()
    {
        $this->credentials = new GCECredentials();
    }

    public function username()
    {
        return cache()->store('array')->rememberForever(
            __CLASS__.'__'.__METHOD__,
            fn () => Str::before($this->credentials->getClientName(), '@')
        );
    }

    public function password()
    {
        return cache()->store('array')->remember(
            __CLASS__.'__'.__METHOD__,
            fn ($result) => (new DateTime())->setTimestamp($result['expire_at']),
            fn ()        => $this->credentials->fetchAuthToken()
        )['access_token'];
    }
}
