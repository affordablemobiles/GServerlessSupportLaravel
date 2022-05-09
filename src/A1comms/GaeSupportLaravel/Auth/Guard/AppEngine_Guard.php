<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Auth\Guard;

use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;

class AppEngine_Guard extends BaseGuard
{
    /**
     * Authenticate a user based on request information,
     * return a valid user object if successful, or null.
     *
     * @return null|\Illuminate\Contracts\Auth\Authenticatable
     */
    public static function validate(Request $request, UserProvider $provider = null)
    {
        $queueName = $request->header('X-AppEngine-QueueName');
        $cron      = $request->header('X-AppEngine-Cron');

        if (!empty($cron)) {
            return static::returnUser($provider, 'cron@appengine.google.internal');
        }
        if (!empty($queueName)) {
            return static::returnUser($provider, $queueName.'@appengine.google.internal');
        }

        return null;
    }
}
