<?php

namespace A1comms\GaeSupportLaravel\Auth\Token\Type;

use Exception;
use GuzzleHttp\Client;
use SimpleJWT\JWT as JWTValidator;
use SimpleJWT\Keys\KeySet;
use SimpleJWT\InvalidTokenException as JWTInvalidTokenException;
use Google\Cloud\Core\ExponentialBackoff;
use A1comms\GaeSupportLaravel\Integration\Guzzle\Tools as GuzzleTools;
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
        $jwkset = static::get_jwk_set($jwk_url);

        try {
            $jwt = JWTValidator::decode($jwt, $jwkset, $sig_alg);
        } catch (JWTInvalidTokenException $e) {
            throw new InvalidTokenException($e->getMessage(), $e->getCode(), $e);
        }

        // Validate token by checking issuer and audience fields.
        try {
            if (!in_array($jwt->getClaim('iss'), $issuers)) {
                throw new InvalidTokenException("Invalid Issuer Claim (iss)");
            }
        } catch (Exception $e) {
            throw new InvalidTokenException("Invalid Claim (iss): " . $e->getMessage(), 0, $e);
        }
        try {
            if ($jwt->getClaim('aud') != $expected_audience) {
                throw new InvalidTokenException("Invalid Target Audience (aud)");
            }
        } catch (Exception $e) {
            throw new InvalidTokenException("Invalid Claim (aud): " . $e->getMessage(), 0, $e);
        }

        // Also check
        try {
            $email = $jwt->getClaim('email');
            if (empty($email)) {
                throw new InvalidTokenException("Email Claim Empty (email)");
            }
        } catch (Exception $e) {
            throw new InvalidTokenException("Invalid Claim (email): " . $e->getMessage(), 0, $e);
        }
        try {
            $sub = $jwt->getClaim('sub');
            if (empty($sub)) {
                throw new InvalidTokenException("Subject Claim Empty (sub)");
            }
        } catch (Exception $e) {
            throw new InvalidTokenException("Invalid Claim (sub): " . $e->getMessage(), 0, $e);
        }

        // Return the user identity (subject and user email) if JWT verification is successful.
        return $jwt->getClaims();
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
        return InstanceLocalCache::remember('jwk_set__' . $jwk_url, 86400, function () use ($jwk_url) {
            $httpclient = new Client();

            $content = [
                'connect_timeout' => self::REQUEST_CONNECTION_TIMEOUT_S,
                'timeout' => self::REQUEST_TIMEOUT_S,
            ];

            $response = (new ExponentialBackoff(6, [JWT::class, 'shouldRetry']))->execute([$httpclient, 'request'], ['GET', $jwk_url, $content]);

            return ((string) $response->getBody());
        });
    }

    public static function shouldRetry($ex, $retryAttempt = 1)
    {
        if (GuzzleTools::isConnectionError($ex, self::REQUEST_CONNECTION_TIMEOUT_S)) {
            return true;
        }

        return false;
    }
}
