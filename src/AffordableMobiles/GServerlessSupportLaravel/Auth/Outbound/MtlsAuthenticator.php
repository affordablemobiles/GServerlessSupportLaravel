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
     */
    public function __construct(
        protected readonly array|string $cert,
        protected readonly null|array|string $key = null,
        protected readonly ?string $certPassword = null,
        protected readonly ?string $keyPassword = null,
    ) {}

    public function authenticate(RequestInterface $request, array &$options = []): RequestInterface
    {
        $options['cert'] = CertificateMaterializer::materialize($this->cert, $this->certPassword);

        if (null !== $this->key && '' !== $this->key) {
            $options['ssl_key'] = CertificateMaterializer::materialize($this->key, $this->keyPassword);
        }

        return $request;
    }
}
