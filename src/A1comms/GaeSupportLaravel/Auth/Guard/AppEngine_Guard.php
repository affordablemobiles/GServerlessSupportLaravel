<?php

namespace A1comms\GaeSupportLaravel\Auth\Guard;

use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\UserProvider;

class AppEngine_Guard extends BaseGuard
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
        $queueName = $request->header('X-AppEngine-QueueName');
        $cron = $request->header('X-AppEngine-Cron');

        if (!empty($cron)) {
            return static::returnUser($provider, 'cron@appengine.google.internal');
        } elseif (!empty($queueName)) {
            return static::returnUser($provider, $queueName . '@appengine.google.internal');
        }

        return null;
    }
}
