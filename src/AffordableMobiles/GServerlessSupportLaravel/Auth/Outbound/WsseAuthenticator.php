<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Contracts\RequestAuthenticatorInterface;
use Psr\Http\Message\RequestInterface;

class WsseAuthenticator implements RequestAuthenticatorInterface
{
    /**
     * @param string $username     Username / API User
     * @param string $secret       Shared Secret / API Key
     * @param string $headerName   Header name (default 'X-WSSE')
     * @param bool   $binaryDigest Whether to compute SHA-1 in raw binary before base64 encoding (default false for hex digest)
     * @param int    $nonceLength  Length of random nonce string (default 32)
     */
    public function __construct(
        protected readonly string $username,
        protected readonly string $secret,
        protected readonly string $headerName = 'X-WSSE',
        protected readonly bool $binaryDigest = false,
        protected readonly int $nonceLength = 32,
    ) {}

    public function authenticate(RequestInterface $request, array &$options = []): RequestInterface
    {
        $nonce   = bin2hex(random_bytes((int) ceil($this->nonceLength / 2)));
        $created = gmdate('Y-m-d\TH:i:s\Z');

        $rawSha1 = sha1($nonce.$created.$this->secret, $this->binaryDigest);
        $digest  = base64_encode($rawSha1);

        $wsseHeader = \sprintf(
            'UsernameToken Username="%s", PasswordDigest="%s", Nonce="%s", Created="%s"',
            $this->username,
            $digest,
            $nonce,
            $created
        );

        return $request->withHeader($this->headerName, $wsseHeader);
    }
}
