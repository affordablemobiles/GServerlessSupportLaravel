<?php

namespace A1comms\GaeSupportLaravel\Auth\Guard;

use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\UserProvider;
use A1comms\GaeSupportLaravel\Auth\Model\IAPUser;
use A1comms\GaeSupportLaravel\Auth\Contracts\Guard\StatelessValidator;

class BaseGuard implements StatelessValidator
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
        return null;
    }

    protected static function returnUser(UserProvider $provider = null, string $email)
    {
        if (empty($provider)){
            $user = new IAPUser();

            $user->fill([
                $user->getAuthIdentifierName() => $email,
            ]);

            return $user;
        } else {
            return $provider->retrieveById($email);
        }
    }
}