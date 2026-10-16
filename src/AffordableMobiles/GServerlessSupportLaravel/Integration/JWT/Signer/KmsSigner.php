<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Integration\JWT\Signer;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Exception\SigningException;
use Google\Cloud\Kms\V1\AsymmetricSignRequest;
use Google\Cloud\Kms\V1\Client\KeyManagementServiceClient;
use Google\Cloud\Kms\V1\Digest;

class KmsSigner implements JwtSignerInterface
{
    /**
     * @param string                          $keyVersionName Full KMS resource path (projects/.../cryptoKeyVersions/...)
     * @param null|string                     $kid            Optional Key ID for JWT header
     * @param string                          $algorithm      Signature algorithm (default 'RS256')
     * @param null|KeyManagementServiceClient $kmsClient      Optional injected KMS client
     */
    public function __construct(
        protected readonly string $keyVersionName,
        protected readonly ?string $kid = null,
        protected readonly string $algorithm = 'RS256',
        protected ?KeyManagementServiceClient $kmsClient = null,
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
        try {
            $client = $this->kmsClient ?? new KeyManagementServiceClient();

            $sha256 = hash('sha256', $signingInput, true);
            $digest = (new Digest())->setSha256($sha256);

            $request = (new AsymmetricSignRequest())
                ->setName($this->keyVersionName)
                ->setDigest($digest)
            ;

            $response = $client->asymmetricSign($request);

            return $response->getSignature();
        } catch (\Throwable $e) {
            throw new SigningException(
                \sprintf('Google Cloud KMS asymmetricSign failed for key [%s]: %s', $this->keyVersionName, $e->getMessage()),
                (int) $e->getCode(),
                $e,
            );
        }
    }
}
