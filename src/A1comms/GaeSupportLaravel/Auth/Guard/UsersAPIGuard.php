<?php

namespace A1comms\GaeSupportLaravel\Auth\Guard;

use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\UserProvider;
use A1comms\GaeSupportLaravel\Auth\Model\IAPUser;
use A1comms\GaeSupportLaravel\Auth\Contracts\Guard\StatelessValidator;

class UsersAPIGuard implements StatelessValidator
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
        $queueName = $request->header('X-AppEngine-QueueName');
        $cron = $request->header('X-AppEngine-Cron');

        if (!empty($email)) {
            return static::returnUser($provider, $email);
        } else if (!empty($queueName)) {
            return static::returnUser($provider, $queueName . '@appengine.google.internal');
        } else if (!empty($cron)) {
            return static::returnUser($provider, 'cron@appengine.google.internal');
        }

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