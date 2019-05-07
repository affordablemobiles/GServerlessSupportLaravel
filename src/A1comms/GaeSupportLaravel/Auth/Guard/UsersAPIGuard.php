<?php

namespace A1comms\GaeSupportLaravel\Auth\Guard;

use Illuminate\Http\Request;
use A1comms\GaeSupportLaravel\Auth\Model\IAPUser;
use A1comms\GaeSupportLaravel\Auth\Guard\Contracts\StatelessValidator;

class UsersAPIGuard implements StatelessValidator
{
    /**
     * Authenticate a user based on request information,
     * return a valid user object if successful, or null.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public static function validate(Request $request)
    {
        $email = $request->header('X-AppEngine-User-Email');

        if (!empty($email)) {
            return new IAPUser($email);
        }

        return null;
    }
}