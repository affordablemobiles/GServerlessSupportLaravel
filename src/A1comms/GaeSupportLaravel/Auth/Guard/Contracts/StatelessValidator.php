<?php

namespace A1comms\GaeSupportLaravel\Auth\Guard\Contracts;

use Illuminate\Http\Request;

interface StatelessValidator
{
    /**
     * Authenticate a user based on request information,
     * return a valid user object if successful, or null.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public static function validate(Request $request);
}