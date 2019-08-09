<?php

namespace A1comms\GaeSupportLaravel\Auth\Guard;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Auth\UserProvider;
use A1comms\GaeSupportLaravel\Auth\Token\IAP;
use A1comms\GaeSupportLaravel\Auth\Exception\InvalidTokenException;

class IAP_Guard extends BaseGuard
{
    /**
     * Authenticate a user based on request information,
     * return a valid user object if successful, or null.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public static function validate(Request $request, UserProvider $provider = null)
    {
        $expected_audience = env('IAP_AUDIENCE');
        if (empty($expected_audience)) {
            throw new Exception("IAP Authentication Guard: Audience (env IAP_AUDIENCE) not defined");
        }

        $jwt = $request->header('X-Goog-IAP-JWT-Assertion');
        if (empty($jwt)) {
            return null;
        }

        try {
            $return = IAP::validateToken($jwt, $expected_audience);
        } catch (InvalidTokenException $e) {
            Log::warning('IAP Authentication Guard: ' . $e->getMessage());
            
            return null;
        }

        return static::returnUser($provider, $return['email']);
    }
}