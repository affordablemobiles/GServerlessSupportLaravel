<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Auth\Http\Controllers\Concerns;

use A1comms\GaeSupportLaravel\Auth\Token\Firebase as Token;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cookie;

trait HandlesFirebaseLogin
{
    /**
     * fetchSessionToken.
     */
    protected function fetchSessionToken(string $idToken, string|null $tenantId = null): string
    {
        return Token::fetchToken(
            env('FIREBASE_PROJECT'),
            $idToken,
            (3600 * 24 * 7),
            $tenantId,
        );
    }

    /**
     * attachLoginCookie.
     */
    protected function attachLoginCookie(Response $response, string $token): Response
    {
        return $response->cookie(
            config('gaesupport.auth.firebase.cookie_name'),
            $token,
            2628000,
            null,
            null,
            true,
            true,
            false,
            'strict'
        );
    }

    /**
     * forgetLoginCookie.
     */
    protected function forgetLoginCookie(): void
    {
        Cookie::queue(
            Cookie::forget(
                config('gaesupport.auth.firebase.cookie_name')
            )
        );
    }

    /**
     * logoutRedirect.
     */
    protected function logoutRedirect(): Response
    {
        return redirect(
            config('gaesupport.auth.firebase.logout_redirect')
        );
    }
}
