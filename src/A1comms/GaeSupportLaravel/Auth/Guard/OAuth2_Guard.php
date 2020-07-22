<?php

namespace A1comms\GaeSupportLaravel\Auth\Guard;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Auth\UserProvider;
use A1comms\GaeSupportLaravel\Auth\Token\OAuth2;
use A1comms\GaeSupportLaravel\Auth\Exception\InvalidTokenException;
use A1comms\GaeSupportLaravel\Integration\ErrorReporting\Report as ErrorReporting;

class OAuth2_Guard extends BaseGuard
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
        $token = $request->bearerToken();
        if (empty($token)) {
            return null;
        }
        
        try {
            $return = OAuth2::validateToken($token);
        } catch (InvalidTokenException $e) {
            Log::warning('OAuth2 Authentication Guard: ' . $e->getMessage());
            
            return null;
        }

        ErrorReporting::exceptionHandler((new Exception("Request is using legacy OAuth2 authentication token")), 200);
        return static::returnUser($provider, $return['email']);
    }
}
