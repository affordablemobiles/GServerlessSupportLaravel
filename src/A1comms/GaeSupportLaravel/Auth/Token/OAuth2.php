<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Auth\Token;

use A1comms\GaeSupportLaravel\Auth\Exception\InvalidTokenException;
use A1comms\GaeSupportLaravel\Integration\Guzzle\Tools as GuzzleTools;
use Google\Auth\Credentials\GCECredentials;
use Google\Cloud\Core\ExponentialBackoff;
use GuzzleHttp\Client;

class OAuth2
{
    /**
     * Connection timeout when speaking to
     * the metadata server.
     */
    public const VALIDATE_CONNECTION_TIMEOUT_S = 0.5;

    /**
     * Timeout for the whole request when talking
     * to the metadata server.
     */
    public const VALIDATE_REQUEST_TIMEOUT_S = 1;

    /**
     * The URI of the token info endpoint.
     */
    public const TOKENINFO_ENDPOINT_URI = 'https://oauth2.googleapis.com/tokeninfo';

    /**
     * Fetch an OAuth2 access_token,
     * which via the metadata server can't have custom scopes.
     *
     * @return string
     */
    public static function fetchToken()
    {
        $g = new GCECredentials();

        return $g->fetchAuthToken()['access_token'];
    }

    /**
     * Validate an OAuth2 access_token.
     *
     * @param string the access_token to validate
     * @param mixed $token
     *
     * @return array returns decoded token information from the tokeninfo endpoint
     *
     * @throws \A1comms\GaeSupportLaravel\Auth\Exception\InvalidTokenException if the token is invalid
     */
    public static function validateToken($token)
    {
        $response = null;

        try {
            $clientParams = [
                'allow_redirects' => false,
                'connect_timeout' => self::VALIDATE_CONNECTION_TIMEOUT_S,
                'timeout'         => self::VALIDATE_REQUEST_TIMEOUT_S,
            ];

            // create the HTTP client
            $client = new Client($clientParams);

            // make the request
            $response = (new ExponentialBackoff(6, [self::class, 'shouldRetry']))->execute([$client, 'get'], [
                self::TOKENINFO_ENDPOINT_URI.'?access_token='.$token,
            ]);

            $response = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            throw new InvalidTokenException('Access Token Validation Exception: Remote Token Check Failed');
        }

        return $response;
    }

    public static function shouldRetry($ex, $retryAttempt = 1)
    {
        if (GuzzleTools::isConnectionError($ex, self::VALIDATE_CONNECTION_TIMEOUT_S)) {
            return true;
        }

        return false;
    }
}
