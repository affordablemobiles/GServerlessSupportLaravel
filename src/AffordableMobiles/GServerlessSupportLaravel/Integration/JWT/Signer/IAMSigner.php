<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Integration\JWT\Signer;

use Google\Auth\ApplicationDefaultCredentials;
use Google\Auth\Credentials\GCECredentials;
use Google\Auth\Iam;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Key;

/**
 * Sign with a Google Service Account using the IAM API.
 *
 * You can grab the JWKS public key definition for a service account
 * by visiting:
 *
 * https://www.googleapis.com/service_accounts/v1/metadata/jwk/{ACCOUNT_EMAIL}
 */
class IAMSigner implements Signer
{
    /**
     * Returns the algorithm id.
     */
    public function algorithmId(): string
    {
        return 'RS256';
    }

    /**
     * Apply changes on headers according with algorithm.
     */
    public function modifyHeader(array &$headers): void
    {
        $headers['alg'] = $this->algorithmId();
    }

    /**
     * Returns a signature for given data.
     *
     * @throws CannotSignPayload  when payload signing fails
     * @throws InvalidKeyProvided when issue key is invalid/incompatible
     * @throws ConversionFailed   when signature could not be converted
     */
    public function sign(string $payload, Key $key): string
    {
        return $this->createHash($payload, $key);
    }

    /**
     * Returns if the expected hash matches with the data and key.
     *
     * @throws InvalidKeyProvided when issue key is invalid/incompatible
     * @throws ConversionFailed   when signature could not be converted
     */
    public function verify(string $expected, string $payload, Key $key): bool
    {
        return $this->doVerify($expected, $payload, $key);
    }

    /**
     * Creates a hash with the given data.
     *
     * @internal
     *
     * @param string $payload
     *
     * @return string
     */
    public function createHash($payload, Key $key)
    {
        $iam = new Iam();

        $gce = new GCECredentials();
        if ($gce->onGce()) {
            $accessToken = $gce->fetchAuthToken()['access_token'];
        } else {
            $creds = ApplicationDefaultCredentials::getCredentials([
                'https://www.googleapis.com/auth/cloud-platform',
            ]);
            $accessToken = $creds->fetchAuthToken()['access_token'];
        }

        $serviceAccount   = $key->contents();
        $signedBlobBase64 = $iam->signBlob($serviceAccount, $accessToken, $payload);

        return base64_decode($signedBlobBase64, true);
    }

    /**
     * Performs the signature verification.
     *
     * @internal
     *
     * @param string $expected
     * @param string $payload
     *
     * @return bool
     */
    public function doVerify($expected, $payload, Key $key)
    {
        throw new \Exception('signature verification is currently unsupported');
    }
}
