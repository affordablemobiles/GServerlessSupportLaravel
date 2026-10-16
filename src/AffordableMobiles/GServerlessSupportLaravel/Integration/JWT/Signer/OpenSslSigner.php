<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Integration\JWT\Signer;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Exception\SigningException;

class OpenSslSigner implements JwtSignerInterface
{
    /**
     * @param string      $privateKey PEM-encoded private key content
     * @param null|string $passphrase Optional passphrase for encrypted PEM
     * @param null|string $kid        Optional Key ID for JWT header
     * @param string      $algorithm  Signature algorithm (default 'RS256')
     */
    public function __construct(
        protected readonly string $privateKey,
        protected readonly ?string $passphrase = null,
        protected readonly ?string $kid = null,
        protected readonly string $algorithm = 'RS256',
    ) {}

    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    public function getKid(): ?string
    {
        return $this->kid;
    }

    public function sign(string $signingInput): string
    {
        $key = openssl_pkey_get_private($this->privateKey, $this->passphrase ?? '');
        if (false === $key) {
            throw new SigningException('Failed to load OpenSSL private key: '.(openssl_error_string() ?: 'invalid key format'));
        }

        $algo = match (strtoupper($this->algorithm)) {
            'RS256' => OPENSSL_ALGO_SHA256,
            'RS384' => OPENSSL_ALGO_SHA384,
            'RS512' => OPENSSL_ALGO_SHA512,
            default => throw new SigningException(\sprintf('Unsupported OpenSSL algorithm [%s]', $this->algorithm)),
        };

        $signature = '';
        if (!openssl_sign($signingInput, $signature, $key, $algo)) {
            throw new SigningException('OpenSSL signing failed: '.(openssl_error_string() ?: 'unknown error'));
        }

        return $signature;
    }
}
