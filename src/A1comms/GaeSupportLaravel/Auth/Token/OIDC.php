<?php

namespace A1comms\GaeSupportLaravel\Auth\Token;

use GuzzleHttp\Client;
use A1comms\GaeSupportLaravel\Auth\Token\Type\JWT;
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
     * JWT Signature Algorithm
     */
    const JWT_SIG_ALG = 'RS256';

    /**
     * List of acceptable JWT issuers
     */
    const JWT_ISSUERS = [
        'https://accounts.google.com',
        'accounts.google.com'
    ];

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
     * @throws \A1comms\GaeSupportLaravel\Auth\Exception\InvalidTokenException if the token is invalid.
     *
     * @return array Returns array containing "sub" and "email" if token is valid.
     */
    public static function validateToken($oidc_jwt, $expected_audience)
    {
        $jwk_url = self::get_jwk_url();

        return JWT::validate($oidc_jwt, $expected_audience, $jwk_url, self::JWT_SIG_ALG, self::JWT_ISSUERS);
    }

    /**
     * The full uri for accessing the public token signing keys (JWK).
     *
     * @return string
     */
    protected static function get_jwk_url()
    {
        return InstanceLocalCache::remember('jwk_url__' . self::OPENID_CONFIGURATION_URI, 60, function () {
            $httpclient = new Client();

            $content = [
                'connect_timeout' => JWT::REQUEST_CONNECTION_TIMEOUT_S,
                'timeout' => JWT::REQUEST_TIMEOUT_S,
            ];

            $response = $httpclient->request('GET', self::OPENID_CONFIGURATION_URI, $content);

            $result = json_decode((string) $response->getBody(), true);

            return $result['jwks_uri'];
        });
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
