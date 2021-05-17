<?php

namespace A1comms\GaeSupportLaravel\Auth\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;
use A1comms\GaeSupportLaravel\Auth\Token\Firebase as Token;

class Firebase extends BaseController
{
    /**
     * login
     *
     * @access public
     *
     * @return void
     */
    public function login()
    {
        $cookie = Token::fetchToken(
            env('FIREBASE_PROJECT'),
            request()->input('idToken')
        );
    
        return response('OK')->cookie(
            '__identity_session', $cookie, 2628000, null, null, true, true, false, 'strict'
        );
    }
}
