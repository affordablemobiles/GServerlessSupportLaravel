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
}
