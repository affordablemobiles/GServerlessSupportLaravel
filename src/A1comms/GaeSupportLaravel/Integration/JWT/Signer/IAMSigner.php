<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Integration\JWT\Signer;

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
        $client = new \Google_Client();

        $client->setApplicationName('GaeSupportLaravel-JWT/0.1');
        $client->useApplicationDefaultCredentials();
        $client->addScope('https://www.googleapis.com/auth/cloud-platform');

        $service = new \Google_Service_IAMCredentials($client);

        $keyID = \sprintf('projects/-/serviceAccounts/%s', $key->contents());

        $requestBody = new \Google_Service_IAMCredentials_SignBlobRequest();

        $requestBody->setPayload(base64_encode($payload));

        $response = $service->projects_serviceAccounts->signBlob($keyID, $requestBody);

        return base64_decode($response->getSignedBlob(), true);
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
