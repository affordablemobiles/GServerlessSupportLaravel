<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Auth\Contracts\Guard;

use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;

interface StatelessValidator
{
    /**
     * Authenticate a user based on request information,
     * return a valid user object if successful, or null.
     *
     * @param \Illuminate\Http\Request $provider
     *
     * @return null|\Illuminate\Contracts\Auth\Authenticatable
     */
    public static function validate(Request $request, UserProvider $provider = null);
}
