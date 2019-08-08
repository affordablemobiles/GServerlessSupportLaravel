<?php

namespace A1comms\GaeSupportLaravel\Auth\Token;

use GuzzleHttp\Client;
use SimpleJWT\JWT;
use SimpleJWT\Keys\KeySet;
use SimpleJWT\InvalidTokenException as JWTInvalidTokenException;
use A1comms\GaeSupportLaravel\Auth\Exception\InvalidTokenException;
use A1comms\GaeSupportLaravel\Cache\InstanceLocal as InstanceLocalCache;

class OIDC
{
    /**
     * The metadata IP address on appengine instances.
     *
     * The IP is used instead of the domain 'metadata' to avoid slow responses
     * when not on Compute Engine.
     */
    const METADATA_HOST = '169.254.169.254';

    /**
     * Connection timeout when speaking to
     * the metadata server.
     */
    const METADATA_CONNECTION_TIMEOUT_S = 0.5;

    /**
     * Timeout for the whole request when talking
     * to the metadata server.
     */
    const METADATA_REQUEST_TIMEOUT_S = 1;

    /**
     * The metadata path of the default identity token.
     */
    const IDENTITY_TOKEN_URI_PATH = 'v1/instance/service-accounts/default/identity';

    /**
     * URI of the public OpenID configuration definition
     */
    const OPENID_CONFIGURATION_URI = 'https://accounts.google.com/.well-known/openid-configuration';

    /**
     * Fetch an OIDC ID token.
     *
     * @param string $target_audience The target audience of the generated JWT.
     * 
     * @return string
     */
    public static function fetchToken($target_audience = '')
    {
        $uri = self::getFetchTokenUri() . '?audience=' . urlencode($target_audience) . '&format=full';

        return self::getFromMetadata($uri);
    }

    /**
     * Validate an OIDC ID token.
     *
     * @param string $oidc_jwt The JWT token to be validated.
     * @param string $expected_audience The expected audience of the provided JWT.
     * 
     * @throws \SimpleJWT\InvalidTokenException if the token is invalid.
     * 
     * @return array Returns array containing "sub" and "email" if token is valid.
     */
    public static function validateToken($oidc_jwt, $expected_audience)
    {
        $jwkset = self::get_jwk_set();

        // Validate the signature using the key set and RS256 algorithm.
        try {
            $jwt = JWT::decode($oidc_jwt, $jwkset, 'RS256');
        } catch (JWTInvalidTokenException $e) {
            throw new InvalidTokenException($e->getMessage(), $e->getCode(), $e);
        }

        // Validate token by checking issuer and audience fields.
        switch ($jwt->getClaim('iss')) {
            case 'https://accounts.google.com':
            case 'accounts.google.com':
                break;
            default:
                throw new InvalidTokenException("Invalid Issuer Claim (iss)");
        }
        if ($jwt->getClaim('aud') != $expected_audience) {
            throw new InvalidTokenException("Invalid Target Audience (aud)");
        }

        $email = $jwt->getClaim('email');
        if (empty($email)) {
            throw new InvalidTokenException("Email Claim Empty (email)");
        }
        $sub = $jwt->getClaim('sub');
        if (empty($sub)) {
            throw new InvalidTokenException("Subject Claim Empty (sub)");
        }

        // Return the user identity (subject and user email) if JWT verification is successful.
        return array('sub' => $sub, 'email' => $email);
    }

    /**
     * Fetches a KeySet instance for the public JWKs.
     *
     * @return \SimpleJWT\Keys\KeySet
     */
    protected static function get_jwk_set()
    {
        // Create a JWK Key Set from the gstatic URL
        $jwkset = new KeySet();
        $jwkset->load(self::get_jwk_set_raw());

        return $jwkset;
    }

    /**
     * Fetches the raw json encoded data for the public JWKs.
     *
     * @return string
     */
    protected static function get_jwk_set_raw()
    {
        // get the public key JWK Set object (RFC7517)
        return InstanceLocalCache::remember('google_oidc_jwk_set', 3600, function () {
            $httpclient = new Client();
            $response = $httpclient->request('GET', self::get_jwk_url(), []);

            return ((string) $response->getBody());
        });
    }

    /**
     * The full uri for accessing the public token signing keys (JWK).
     *
     * @return string
     */
    protected static function get_jwk_url()
    {
        $httpclient = new Client();

        $content = [
            'connect_timeout' => self::METADATA_CONNECTION_TIMEOUT_S,
            'timeout' => self::METADATA_REQUEST_TIMEOUT_S,
        ];

        $response = $httpclient->request('GET', self::OPENID_CONFIGURATION_URI, $content);

        $result = json_decode((string) $response->getBody(), true);

        return $result['jwks_uri'];
    }

    /**
     * The full uri for accessing the default token.
     *
     * @return string
     */
    protected static function getFetchTokenUri()
    {
        $base = 'http://' . self::METADATA_HOST . '/computeMetadata/';

        return $base . self::IDENTITY_TOKEN_URI_PATH;
    }

    /**
     * Fetch the value of a GCE metadata server URI.
     *
     * @param string $uri The metadata URI.
     * 
     * @return string
     */
    protected static function getFromMetadata($uri)
    {
        $client = new Client();

        $content = [
            'connect_timeout' => self::METADATA_CONNECTION_TIMEOUT_S,
            'timeout' => self::METADATA_REQUEST_TIMEOUT_S,
            'headers' => [
                'Metadata-Flavor' => 'Google',
            ],
        ];

        $response = $client->get($uri, $content);

        return ((string) $response->getBody());
    }
}