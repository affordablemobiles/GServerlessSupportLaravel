<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Contracts\RequestAuthenticatorInterface;
use Psr\Http\Message\RequestInterface;

class ChainAuthenticator implements RequestAuthenticatorInterface
{
    /**
     * @param array<RequestAuthenticatorInterface> $authenticators
     */
    public function __construct(
        protected readonly array $authenticators,
    ) {}

    public function authenticate(RequestInterface $request, array &$options = []): RequestInterface
    {
        foreach ($this->authenticators as $authenticator) {
            if ($authenticator instanceof RequestAuthenticatorInterface) {
                $request = $authenticator->authenticate($request, $options);
            }
        }

        return $request;
    }

    /**
     * Invalidate cached tokens across all child authenticators.
     */
    public function invalidateToken(): bool
    {
        $invalidated = false;

        foreach ($this->authenticators as $authenticator) {
            if ($authenticator instanceof AbstractOAuth2Authenticator) {
                if ($authenticator->invalidateToken()) {
                    $invalidated = true;
                }
            } elseif (method_exists($authenticator, 'invalidateToken')) {
                if ($authenticator->invalidateToken()) {
                    $invalidated = true;
                }
            }
        }

        return $invalidated;
    }

    /**
     * Check if any child authenticator is an OAuth2 authenticator supporting token invalidation.
     */
    public function hasOAuth2Authenticator(): bool
    {
        foreach ($this->authenticators as $authenticator) {
            if ($authenticator instanceof AbstractOAuth2Authenticator) {
                return true;
            }

            if ($authenticator instanceof self && $authenticator->hasOAuth2Authenticator()) {
                return true;
            }
        }

        return false;
    }
}
