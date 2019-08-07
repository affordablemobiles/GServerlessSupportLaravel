<?php

namespace A1comms\GaeSupportLaravel\Auth\Guard;

use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\UserProvider;

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
        $email = $request->header('X-AppEngine-User-Email');
        if (!empty($email)) {
            return static::returnUser($provider, $email);
        }

        return null;
    }
}