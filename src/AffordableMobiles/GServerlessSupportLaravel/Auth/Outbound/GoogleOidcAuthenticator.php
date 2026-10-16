<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Contracts\RequestAuthenticatorInterface;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Token\OIDC;
use Psr\Http\Message\RequestInterface;

class GoogleOidcAuthenticator implements RequestAuthenticatorInterface
{
    /**
     * @param callable|string $audience target audience string or callable resolving it from RequestInterface
     */
    public function __construct(
        protected readonly mixed $audience,
    ) {}

    public function authenticate(RequestInterface $request, array $options = []): RequestInterface
    {
        $targetAudience = \is_callable($this->audience)
            ? ($this->audience)($request)
            : (string) $this->audience;

        $token = OIDC::fetchToken($targetAudience);

        return $request->withHeader('Authorization', 'Bearer '.$token);
    }
}
