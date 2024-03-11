<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Http\Controllers;

use Illuminate\Http\RedirectResponse;
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

        $this->attachLoginCookie($token);

        return response('OK');
    }

    /**
     * logout.
     */
    public function logout(): RedirectResponse
    {
        $this->forgetLoginCookie();

        return $this->logoutRedirect();
    }
}
