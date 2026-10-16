<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Integration\JWT\Signer;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Exception\SigningException;
use Google\Auth\ApplicationDefaultCredentials;
use Google\Auth\Credentials\GCECredentials;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Cache;

class IamSigner implements JwtSignerInterface
{
    protected ?string $activeKid = null;

    /**
     * @param string      $serviceAccountEmail Service account email to sign with
     * @param null|string $kid                 Optional Key ID for JWT header (if null, auto-discovered from IAM response)
     */
    public function __construct(
        protected readonly string $serviceAccountEmail,
        protected readonly ?string $kid = null,
    ) {}

    public function getAlgorithm(): string
    {
        return 'RS256';
    }

    public function getKid(): ?string
    {
        if (null !== $this->kid && '' !== $this->kid) {
            return $this->kid;
        }

        if (null !== $this->activeKid) {
            return $this->activeKid;
        }

        try {
            $cached = Cache::store('instance-scoped')->get($this->getKidCacheKey());
            if (\is_string($cached) && '' !== $cached) {
                return $this->activeKid = $cached;
            }
        } catch (\Throwable) {
            // Ignore cache resolution failure
        }

        return null;
    }

    public function sign(string $signingInput): string
    {
        try {
            $accessToken = $this->fetchAccessToken();
            $client      = new GuzzleClient([
                'timeout'         => 10,
                'connect_timeout' => 5,
            ]);

            $url = \sprintf(
                'https://iamcredentials.googleapis.com/v1/projects/-/serviceAccounts/%s:signBlob',
                urlencode($this->serviceAccountEmail)
            );

            $response = $client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'payload' => base64_encode($signingInput),
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);

            if (!\is_array($data) || empty($data['signedBlob'])) {
                throw new SigningException('IAM signBlob returned invalid response');
            }

            if (!empty($data['keyId'])) {
                $this->activeKid = (string) $data['keyId'];

                try {
                    Cache::store('instance-scoped')->put($this->getKidCacheKey(), $this->activeKid, 86400 * 7);
                } catch (\Throwable) {
                    // Ignore cache failure
                }
            }

            $decoded = base64_decode((string) $data['signedBlob'], true);
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

    protected function getKidCacheKey(): string
    {
        return 'gcp_iam_active_kid_'.hash('sha256', strtolower($this->serviceAccountEmail));
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
