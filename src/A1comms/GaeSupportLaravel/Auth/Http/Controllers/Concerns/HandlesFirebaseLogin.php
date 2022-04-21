<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Auth\Http\Controllers\Concerns;

use A1comms\GaeSupportLaravel\Auth\Exception\InvalidTokenException;
use A1comms\GaeSupportLaravel\Auth\Token\Firebase as Token;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie as CookieHelper;
use Symfony\Component\HttpFoundation\Cookie;

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
     * fetchSessionTokenFromCookie.
     */
    protected function fetchSessionTokenFromCookie(Request $request): string
    {
        $cookieName = config('gaesupport.auth.firebase.cookie_name');

        if ($request->hasCookie($cookieName)) {
            return $request->cookie($cookieName);
        }

        throw new InvalidTokenException('Cookie Not Found', 0);
    }

    /**
     * attachLoginCookie.
     */
    protected function attachLoginCookie(string $token, int $expiryMinutes = 2628000, string|null $path = null, string|null $domain = null): void
    {
        CookieHelper::queue(
            $this->fetchLoginCookie($token, $expiryMinutes, $path, $domain),
        );
    }

    /**
     * refreshLoginCookie.
     */
    protected function refreshLoginCookie(Request $request, int $expiryMinutes = 2628000, string|null $path = null, string|null $domain = null): void
    {
        $cookieName = config('gaesupport.auth.firebase.cookie_name');

        if ($request->hasCookie($cookieName)) {
            CookieHelper::queue(
                $this->fetchLoginCookie(
                    $request->cookie($cookieName),
                    $expiryMinutes,
                    $path,
                    $domain,
                )
            );
        }
    }

    /**
     * forgetLoginCookie.
     */
    protected function forgetLoginCookie(string|null $path = null, string|null $domain = null): void
    {
        CookieHelper::queue(
            CookieHelper::forget(
                config('gaesupport.auth.firebase.cookie_name'),
                $path,
                $domain,
            )
        );
    }

    /**
     * logoutRedirect.
     */
    protected function logoutRedirect(): RedirectResponse
    {
        return redirect(
            config('gaesupport.auth.firebase.logout_redirect')
        );
    }

    private function fetchLoginCookie(string $token, int $expiryMinutes = 2628000, string|null $path = null, string|null $domain = null): Cookie
    {
        return CookieHelper::make(
            config('gaesupport.auth.firebase.cookie_name'), // name
            $token,                                         // value
            $expiryMinutes,                                 // expiry
            $path,                                          // path
            $domain,                                        // domain
            true,                                           // secure
            true,                                           // httpOnly
            false,                                          // raw
            'strict'                                        // sameSite
        );
    }
}
