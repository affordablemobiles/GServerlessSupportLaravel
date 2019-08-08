<?php

namespace A1comms\GaeSupportLaravel\Auth\Token\Type;

use GuzzleHttp\Client;
use SimpleJWT\JWT as JWTValidator;
use SimpleJWT\Keys\KeySet;
use SimpleJWT\InvalidTokenException as JWTInvalidTokenException;
use A1comms\GaeSupportLaravel\Auth\Exception\InvalidTokenException;
use A1comms\GaeSupportLaravel\Cache\InstanceLocal as InstanceLocalCache;

class JWT
{
    /**
     * Connection timeout for the request.
     */
    const REQUEST_CONNECTION_TIMEOUT_S = 0.5;

    /**
     * Timeout for the whole request.
     */
    const REQUEST_TIMEOUT_S = 1;

    /**
     * Validate an JWT ID token.
     *
     * @param string $jwt The JWT token to be validated.
     * @param string $expected_audience The expected audience of the provided JWT.
     * @param string $jwk_url URL of the JWK public key file.
     * @param string $sig_alg Expected signature algorithm of the JWT.
     * @param array $issuers An array of acceptable issuers.
     * 
     * @throws \A1comms\GaeSupportLaravel\Auth\Exception\InvalidTokenException if the token is invalid.
     * 
     * @return array Returns array containing "sub" and "email" if token is valid.
     */
    public static function validate($jwt, $expected_audience, $jwk_url, $sig_alg, $issuers)
    {
        $jwkset = self::get_jwk_set($jwk_url);

        try {
            $jwt = JWTValidator::decode($jwt, $jwkset, $sig_alg);
        } catch (JWTInvalidTokenException $e) {
            throw new InvalidTokenException($e->getMessage(), $e->getCode(), $e);
        }

        // Validate token by checking issuer and audience fields.
        if (!in_array($jwt->getClaim('iss'), $issuers)) {
            throw new InvalidTokenException("Invalid Issuer Claim (iss)");
        }
        if ($jwt->getClaim('aud') != $expected_audience) {
            throw new InvalidTokenException("Invalid Target Audience (aud)");
        }

        // Also check 
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
     * @param string $jwk_url URL of the JWK public key file.
     * 
     * @return \SimpleJWT\Keys\KeySet
     */
    protected static function get_jwk_set($jwk_url)
    {
        // Create a JWK Key Set from the gstatic URL
        $jwkset = new KeySet();
        $jwkset->load(self::get_jwk_set_raw($jwk_url));

        return $jwkset;
    }

    /**
     * Fetches the raw json encoded data for the public JWKs.
     *
     * @param string $jwk_url URL of the JWK public key file.
     * 
     * @return string
     */
    protected static function get_jwk_set_raw($jwk_url)
    {
        // get the public key JWK Set object (RFC7517)
        return InstanceLocalCache::remember('jwk_set__' . $jwk_url, 3600, function () use ($jwk_url) {
            $httpclient = new Client();

            $content = [
                'connect_timeout' => self::REQUEST_CONNECTION_TIMEOUT_S,
                'timeout' => self::REQUEST_TIMEOUT_S,
            ];

            $response = $httpclient->request('GET', $jwk_url, $content);

            return ((string) $response->getBody());
        });
    }
}