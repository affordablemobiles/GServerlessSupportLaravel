<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Auth\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller as BaseController;

class Firebase extends BaseController
{
    use Concerns\HandlesFirebaseLogin;

    /**
     * login.
     */
    public function login(Request $request): Response
    {
        $token = $this->fetchSessionToken(
            $request->input('idToken'),
        );

        return $this->attachLoginCookie(
            response('OK'),
            $token,
        );
    }

    /**
     * logout.
     */
    public function logout(): Response
    {
        $this->forgetLoginCookie();

        return $this->logoutRedirect();
    }
}
