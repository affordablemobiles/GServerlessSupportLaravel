<?php

namespace A1comms\GaeSupportLaravel\Auth\Token;

use Exception;
use GuzzleHttp\Client;
use Google\Auth\Credentials\GCECredentials;

class OAuth2
{
    /**
     * Connection timeout when speaking to
     * the metadata server.
     */
    const VALIDATE_CONNECTION_TIMEOUT_S = 0.5;

    /**
     * Timeout for the whole request when talking
     * to the metadata server.
     */
    const VALIDATE_REQUEST_TIMEOUT_S = 1;

    /**
     * The URI of the token info endpoint.
     */
    const TOKENINFO_ENDPOINT_URI = 'https://oauth2.googleapis.com/tokeninfo';

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
     * @param string The access_token to validate.
     * 
     * @throws Exception if the token is invalid.
     * 
     * @return array Returns decoded token information from the tokeninfo endpoint.
     */
    public static function validateToken($token)
    {
        $response = null;
        
        try {
            $clientParams = [
                'allow_redirects'   => false,
                'connect_timeout'   => self::VALIDATE_CONNECTION_TIMEOUT_S,
                'timeout'           => self::VALIDATE_REQUEST_TIMEOUT_S,
            ];

            // create the HTTP client
            $client = new Client($clientParams);

            // make the request
            $response = $client->get(
                self::TOKENINFO_ENDPOINT_URI . '?access_token=' . $token
            );

            $response = json_decode($response->getBody(), true);
        } catch (Exception $e) {
            throw new Exception("Access Token Validation Exception: Remote Token Check Failed");
        }

        return $response;
    }
}