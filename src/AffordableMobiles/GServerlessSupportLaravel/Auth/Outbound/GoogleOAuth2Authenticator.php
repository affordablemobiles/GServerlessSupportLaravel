<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Contracts\RequestAuthenticatorInterface;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Token\OAuth2;
use Psr\Http\Message\RequestInterface;

class GoogleOAuth2Authenticator implements RequestAuthenticatorInterface
{
    public function authenticate(RequestInterface $request, array &$options = []): RequestInterface
    {
        $token = OAuth2::fetchToken();

        return $request->withHeader('Authorization', 'Bearer '.$token);
    }
}
