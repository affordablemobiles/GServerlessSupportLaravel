<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Http\Controllers\Concerns;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Exception\InvalidTokenException;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Token\Firebase as Token;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie as CookieHelper;
use Symfony\Component\HttpFoundation\Cookie;

trait HandlesFirebaseLogin
{
    /**
     * fetchSessionToken.
     */
    protected function fetchSessionToken(string $idToken, null|string $tenantId = null): string
    {
        return Token::fetchToken(
            env('FIREBASE_PROJECT'),
            $idToken,
            3600 * 24 * 7,
            $tenantId,
        );
    }

    /**
     * fetchUserData.
     */
    protected function fetchUserData(string $idToken, string $uid, null|string $tenantId = null): array
    {
        return Token::userLookup(
            env('FIREBASE_PROJECT'),
            $idToken,
            $uid,
            $tenantId,
        );
    }

    /**
     * fetchSessionTokenFromCookie.
     */
    protected function fetchSessionTokenFromCookie(Request $request): string
    {
        $cookieName = config('gserverlesssupport.auth.firebase.cookie_name');

        if ($request->hasCookie($cookieName)) {
            return $request->cookie($cookieName);
        }

        throw new InvalidTokenException('Cookie Not Found', 0);
    }

    /**
     * attachLoginCookie.
     */
    protected function attachLoginCookie(string $token, int $expiryMinutes = 2628000, null|string $path = null, null|string $domain = null): void
    {
        CookieHelper::queue(
            $this->fetchLoginCookie($token, $expiryMinutes, $path, $domain),
        );
    }

    /**
     * refreshLoginCookie.
     */
    protected function refreshLoginCookie(Request $request, int $expiryMinutes = 2628000, null|string $path = null, null|string $domain = null): void
    {
        $cookieName = config('gserverlesssupport.auth.firebase.cookie_name');

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
    protected function forgetLoginCookie(null|string $path = null, null|string $domain = null): void
    {
        CookieHelper::queue(
            CookieHelper::forget(
                config('gserverlesssupport.auth.firebase.cookie_name'),
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
            config('gserverlesssupport.auth.firebase.logout_redirect')
        );
    }

    private function fetchLoginCookie(string $token, int $expiryMinutes = 2628000, null|string $path = null, null|string $domain = null): Cookie
    {
        return CookieHelper::make(
            config('gserverlesssupport.auth.firebase.cookie_name'),     // name
            $token,                                             // value
            $expiryMinutes,                                     // expiry
            $path,                                              // path
            $domain,                                            // domain
            true,                                               // secure
            config('gserverlesssupport.auth.firebase.cookie_httpOnly'), // httpOnly
            false,                                              // raw
            config('gserverlesssupport.auth.firebase.cookie_sameSite'), // sameSite
        );
    }
}
