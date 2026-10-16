<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Contracts\RequestAuthenticatorInterface;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Exception\SigningException;
use AffordableMobiles\GServerlessSupportLaravel\Integration\JWT\Signer\JwtSignerInterface;
use Psr\Http\Message\RequestInterface;

class PayloadSignatureAuthenticator implements RequestAuthenticatorInterface
{
    /**
     * @param JwtSignerInterface|string $key             HMAC secret, PEM private key, or JwtSignerInterface instance
     * @param string                    $headerName      Header to store computed signature in
     * @param null|string               $timestampHeader Optional header to store current UNIX timestamp in
     * @param string                    $bodySeparator   Optional separator between timestamp and body (e.g. '|' for Samsung)
     * @param array<string,string>      $extraHeaders    Additional static headers to inject (e.g. partner IDs)
     * @param string                    $algorithm       Signature algorithm ('sha256', 'sha1', etc.)
     * @param string                    $signatureFormat Signature output format ('base64' or 'hex')
     * @param string                    $signerType      Signer type: 'auto', 'hmac', or 'openssl'
     */
    public function __construct(
        protected readonly mixed $key,
        protected readonly string $headerName = 'X-Signature',
        protected readonly ?string $timestampHeader = null,
        protected readonly string $bodySeparator = '',
        protected readonly array $extraHeaders = [],
        protected readonly string $algorithm = 'sha256',
        protected readonly string $signatureFormat = 'base64',
        protected readonly string $signerType = 'auto',
    ) {}

    public function authenticate(RequestInterface $request, array &$options = []): RequestInterface
    {
        $bodyStream = $request->getBody();
        $body       = (string) $bodyStream;

        if ($bodyStream->isSeekable()) {
            $bodyStream->rewind();
        }

        $toSign = $body;

        if (null !== $this->timestampHeader && '' !== $this->timestampHeader) {
            $timestamp = (string) time();
            $request   = $request->withHeader($this->timestampHeader, $timestamp);
            $toSign    = '' !== $this->bodySeparator
                ? $timestamp.$this->bodySeparator.$body
                : $timestamp.$body;
        }

        $signature = $this->computeSignature($toSign);

        $request = $request->withHeader($this->headerName, $signature);

        foreach ($this->extraHeaders as $name => $val) {
            $request = $request->withHeader($name, $val);
        }

        return $request;
    }

    protected function computeSignature(string $data): string
    {
        if ($this->key instanceof JwtSignerInterface) {
            $raw = $this->key->sign($data);

            return 'hex' === $this->signatureFormat ? bin2hex($raw) : base64_encode($raw);
        }

        $keyString = (string) $this->key;

        $isOpenSsl = 'openssl' === $this->signerType
            || str_contains($keyString, 'BEGIN PRIVATE KEY')
            || str_contains($keyString, 'BEGIN RSA PRIVATE KEY');

        if ($isOpenSsl) {
            $pkey = openssl_pkey_get_private($keyString);
            if (false === $pkey) {
                throw new SigningException('Failed to load private key for payload signing');
            }

            $rawSignature = '';
            if (!openssl_sign($data, $rawSignature, $pkey, $this->algorithm)) {
                throw new SigningException('OpenSSL payload signing failed');
            }

            return 'hex' === $this->signatureFormat ? bin2hex($rawSignature) : base64_encode($rawSignature);
        }

        // Default: HMAC signing
        $rawHmac = hash_hmac($this->algorithm, $data, $keyString, true);

        return 'hex' === $this->signatureFormat ? bin2hex($rawHmac) : base64_encode($rawHmac);
    }
}
