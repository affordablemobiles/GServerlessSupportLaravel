<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Guard;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Exception\InvalidTokenException;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Token\OIDC;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OIDC_Guard extends BaseGuard
{
    /**
     * Authenticate a user based on request information,
     * return a valid user object if successful, or null.
     *
     * @return null|\Illuminate\Contracts\Auth\Authenticatable
     */
    public static function validate(Request $request, UserProvider $provider = null)
    {
        $expected_audience = env('OIDC_AUDIENCE');
        if (empty($expected_audience)) {
            throw new \Exception('OIDC Authentication Guard: Audience (env OIDC_AUDIENCE) not defined');
        }

        $jwt = $request->bearerToken();
        if (empty($jwt)) {
            return null;
        }

        try {
            $return = OIDC::validateToken($jwt, $expected_audience);
        } catch (InvalidTokenException $e) {
            Log::warning('OIDC Authentication Guard: '.$e->getMessage());

            return null;
        }

        return static::returnUser($provider, $return['email']);
    }
}
