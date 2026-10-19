<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Contracts\RequestAuthenticatorInterface;
use Psr\Http\Message\RequestInterface;

class BasicAuthenticator implements RequestAuthenticatorInterface
{
    /**
     * @param string      $username   Username, or pre-encoded base64 string if password is null
     * @param null|string $password   Password (optional)
     * @param string      $headerName Header name (default 'Authorization')
     */
    public function __construct(
        protected readonly string $username,
        protected readonly ?string $password = null,
        protected readonly string $headerName = 'Authorization',
    ) {}

    public function authenticate(RequestInterface $request, array &$options = []): RequestInterface
    {
        if (null !== $this->password) {
            $encoded = base64_encode($this->username.':'.$this->password);
        } else {
            // Pre-encoded base64 or username-only token (e.g. "apiKey:")
            $encoded = str_starts_with($this->username, 'Basic ')
                ? substr($this->username, 6)
                : $this->username;
        }

        return $request->withHeader($this->headerName, 'Basic '.$encoded);
    }
}
