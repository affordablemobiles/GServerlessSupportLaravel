<?php

namespace A1comms\GaeSupportLaravel\Integration\JWT\Signer;

use Exception;
use InvalidArgumentException;
use Lcobucci\JWT\Signer\BaseSigner;
use Lcobucci\JWT\Signature;
use Google_Client;
use Google_Service_iam;
use Google_Service_iam_SignBlobRequest;

/**
 * Sign with a Google Service Account using the IAM API
 */
class IAMSigner implements Signer
{
    /**
     * Returns the algorithm id
     *
     * @return string
     */
    public function getAlgorithmId() {
        return "RS256";
    }

    /**
     * Apply changes on headers according with algorithm
     *
     * @param array $headers
     */
    public function modifyHeader(array &$headers)
    {
        $headers['alg'] = $this->getAlgorithmId();
    }

    /**
     * Returns a signature for given data
     *
     * @param string $payload
     * @param string $key
     *
     * @return Signature
     *
     * @throws InvalidArgumentException When given key is invalid
     */
    public function sign($payload, $key)
    {
        return new Signature($this->createHash($payload, $this->getKey($key)));
    }

    /**
     * Returns if the expected hash matches with the data and key
     *
     * @param string $expected
     * @param string $payload
     * @param string $key
     *
     * @return boolean
     *
     * @throws InvalidArgumentException When given key is invalid
     */
    public function verify($expected, $payload, $key)
    {
        return $this->doVerify($expected, $payload, $this->getKey($key));
    }

    /**
     * @param Key|string $key
     *
     * @return Key
     */
    private function getKey($key)
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException("Key must be a string containing the service account ID");
        }

        return $key;
    }

    /**
     * Creates a hash with the given data
     *
     * @internal
     *
     * @param string $payload
     * @param Key $key
     *
     * @return string
     */
    public function createHash($payload, string $key) {
        $client = new Google_Client();
        
        $client->setApplicationName('GaeSupportLaravel-JWT/0.1');
        $client->useApplicationDefaultCredentials();
        $client->addScope('https://www.googleapis.com/auth/cloud-platform');

        $service = new Google_Service_iam($client);

        $keyID = sprintf('projects/-/serviceAccounts/%s', $key);

        $requestBody = new Google_Service_iam_SignBlobRequest();

        $requestBody->setBytesToSign($payload);

        $response = $service->projects_serviceAccounts->signBlob($name, $requestBody);

        return $response->getSignature();
    }

    /**
     * Performs the signature verification
     *
     * @internal
     *
     * @param string $expected
     * @param string $payload
     * @param Key $key
     *
     * @return boolean
     */
    public function doVerify($expected, $payload, string $key) {
        throw new Exception("signature verification is currently unsupported");
    }
}