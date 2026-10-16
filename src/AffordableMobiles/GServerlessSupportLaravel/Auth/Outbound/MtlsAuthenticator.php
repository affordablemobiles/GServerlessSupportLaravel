<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Contracts\RequestAuthenticatorInterface;
use AffordableMobiles\GServerlessSupportLaravel\Support\CertificateMaterializer;
use Psr\Http\Message\RequestInterface;

class MtlsAuthenticator implements RequestAuthenticatorInterface
{
    /**
     * @param array|string      $cert         Client certificate PEM string, file path, or [cert, passphrase]
     * @param null|array|string $key          Private key PEM string or file path (optional if combined in cert)
     * @param null|string       $certPassword Passphrase for client certificate
     * @param null|string       $keyPassword  Passphrase for private key
     * @param bool              $inMemory     Whether to prefer in-memory cURL blobs over disk files (default true)
     */
    public function __construct(
        protected readonly array|string $cert,
        protected readonly null|array|string $key = null,
        protected readonly ?string $certPassword = null,
        protected readonly ?string $keyPassword = null,
        protected readonly bool $inMemory = true,
    ) {}

    public function authenticate(RequestInterface $request, array &$options = []): RequestInterface
    {
        CertificateMaterializer::bindToRequestOptions(
            $options,
            $this->cert,
            $this->key,
            $this->certPassword,
            $this->keyPassword,
            $this->inMemory,
        );

        return $request;
    }
}
