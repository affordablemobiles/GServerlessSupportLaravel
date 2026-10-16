<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Integration\JWT\Signer;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Exception\SigningException;
use Google\Auth\ApplicationDefaultCredentials;
use Google\Auth\Credentials\GCECredentials;
use Google\Auth\Iam;

class IamSigner implements JwtSignerInterface
{
    /**
     * @param string      $serviceAccountEmail Service account email to sign with
     * @param null|string $kid                 Optional Key ID for JWT header
     * @param null|Iam    $iam                 Optional injected IAM helper
     */
    public function __construct(
        protected readonly string $serviceAccountEmail,
        protected readonly ?string $kid = null,
        protected ?Iam $iam = null,
    ) {}

    public function getAlgorithm(): string
    {
        return 'RS256';
    }

    public function getKid(): ?string
    {
        return $this->kid;
    }

    public function sign(string $signingInput): string
    {
        try {
            $iam         = $this->iam ?? new Iam();
            $accessToken = $this->fetchAccessToken();

            $signedBlobBase64 = $iam->signBlob($this->serviceAccountEmail, $accessToken, $signingInput);
            $decoded          = base64_decode($signedBlobBase64, true);

            if (false === $decoded) {
                throw new SigningException('Failed to base64-decode IAM signed blob');
            }

            return $decoded;
        } catch (\Throwable $e) {
            throw new SigningException(
                \sprintf('Google IAM signBlob failed for service account [%s]: %s', $this->serviceAccountEmail, $e->getMessage()),
                (int) $e->getCode(),
                $e,
            );
        }
    }

    protected function fetchAccessToken(): string
    {
        $gce = new GCECredentials();
        if ($gce->onGce()) {
            return $gce->fetchAuthToken()['access_token'];
        }

        $creds = ApplicationDefaultCredentials::getCredentials([
            'https://www.googleapis.com/auth/cloud-platform',
        ]);

        return $creds->fetchAuthToken()['access_token'];
    }
}
