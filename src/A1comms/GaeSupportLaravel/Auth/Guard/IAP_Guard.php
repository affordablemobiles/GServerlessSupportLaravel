<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Auth\Guard;

use A1comms\GaeSupportLaravel\Auth\Exception\InvalidTokenException;
use A1comms\GaeSupportLaravel\Auth\Token\IAP;
use Exception;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IAP_Guard extends BaseGuard
{
    /**
     * Authenticate a user based on request information,
     * return a valid user object if successful, or null.
     *
     * @return null|\Illuminate\Contracts\Auth\Authenticatable
     */
    public static function validate(Request $request, UserProvider $provider = null)
    {
        $expected_audience = env('IAP_AUDIENCE');
        if (empty($expected_audience)) {
            throw new Exception('IAP Authentication Guard: Audience (env IAP_AUDIENCE) not defined');
        }

        $jwt = $request->header('X-Goog-IAP-JWT-Assertion');
        if (empty($jwt)) {
            if (is_cloud_run()) {
                $user = explode(':', $request->header('X-Goog-Authenticated-User-Email'));
                if (count($user) == 2) {
                    return $user[1];
                }
            }

            return null;
        }

        try {
            $return = IAP::validateToken($jwt, $expected_audience);
        } catch (InvalidTokenException $e) {
            Log::warning('IAP Authentication Guard: '.$e->getMessage());

            return null;
        }

        return static::returnUser($provider, $return['email']);
    }
}
