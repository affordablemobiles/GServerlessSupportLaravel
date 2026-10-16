<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Contracts;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Exception\OutboundAuthenticationException;
use Psr\Http\Message\RequestInterface;

interface RequestAuthenticatorInterface
{
    /**
     * Mutates the outbound PSR-7 RequestInterface with authentication headers or parameters,
     * and optionally mutates client options (e.g. client certificates, proxy, verification).
     *
     * @param array<string,mixed> $options
     *
     * @throws OutboundAuthenticationException
     */
    public function authenticate(RequestInterface $request, array &$options = []): RequestInterface;
}
