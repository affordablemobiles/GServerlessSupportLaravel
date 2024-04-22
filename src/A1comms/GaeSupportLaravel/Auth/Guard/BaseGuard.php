<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Auth\Guard;

use A1comms\GaeSupportLaravel\Auth\Contracts\Guard\StatelessValidator;
use A1comms\GaeSupportLaravel\Auth\Model\IAPUser;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;

class BaseGuard implements StatelessValidator
{
    /**
     * Authenticate a user based on request information,
     * return a valid user object if successful, or null.
     *
     * @return null|\Illuminate\Contracts\Auth\Authenticatable
     */
    public static function validate(Request $request, UserProvider $provider = null)
    {
        return null;
    }

    protected static function returnUser(UserProvider $provider = null, string $email)
    {
        if (empty($provider)) {
            $user = new IAPUser();

            $user->fill([
                $user->getAuthIdentifierName() => $email,
            ]);

            return $user;
        }

        return $provider->retrieveById($email);
    }
}
