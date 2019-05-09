<?php

namespace A1comms\GaeSupportLaravel\Auth\Contracts\Guard;

use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\UserProvider;

interface StatelessValidator
{
    /**
     * Authenticate a user based on request information,
     * return a valid user object if successful, or null.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Http\Request  $provider
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public static function validate(Request $request, UserProvider $provider = null);
}