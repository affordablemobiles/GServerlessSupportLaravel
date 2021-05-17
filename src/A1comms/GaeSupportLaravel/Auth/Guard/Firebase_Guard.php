<?php

namespace A1comms\GaeSupportLaravel\Auth\Guard;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Auth\UserProvider;
use A1comms\GaeSupportLaravel\Auth\Token\Firebase;
use A1comms\GaeSupportLaravel\Auth\Exception\InvalidTokenException;

class Firebase_Guard extends BaseGuard
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
        $expected_audience = env('FIREBASE_PROJECT');
        if (empty($expected_audience)) {
            throw new Exception("Firebase Authentication Guard: Audience (env FIREBASE_PROJECT) not defined");
        }

        $jwt = $request->cookie('__identity_session');
        if (empty($jwt)) {
            return null;
        }

        try {
            $return = Firebase::validateToken($jwt, $expected_audience);
        } catch (InvalidTokenException $e) {
            Log::warning('Firebase Authentication Guard: ' . $e->getMessage());
            
            return null;
        }

        return static::returnUser($provider, $return['email']);
    }
}
