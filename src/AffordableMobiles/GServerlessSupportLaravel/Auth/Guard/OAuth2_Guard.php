<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Guard;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Exception\InvalidTokenException;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Token\OAuth2;
use AffordableMobiles\GServerlessSupportLaravel\Integration\ErrorReporting\Report as ErrorReporting;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OAuth2_Guard extends BaseGuard
{
    /**
     * Authenticate a user based on request information,
     * return a valid user object if successful, or null.
     *
     * @return null|\Illuminate\Contracts\Auth\Authenticatable
     */
    public static function validate(Request $request, UserProvider $provider = null)
    {
        $token = $request->bearerToken();
        if (empty($token)) {
            return null;
        }

        try {
            $return = OAuth2::validateToken($token);
        } catch (InvalidTokenException $e) {
            Log::warning('OAuth2 Authentication Guard: '.$e->getMessage());

            return null;
        }

        ErrorReporting::exceptionHandler(new \Exception('Request is using legacy OAuth2 authentication token'), 200);

        return static::returnUser($provider, $return['email']);
    }
}
