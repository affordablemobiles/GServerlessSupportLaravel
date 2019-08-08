<?php

namespace A1comms\GaeSupportLaravel\Auth\Token;

use A1comms\GaeSupportLaravel\Auth\Token\Type\JWT;
use A1comms\GaeSupportLaravel\Auth\Exception\InvalidTokenException;

class IAP
{
    /**
     * URI of the public OpenID configuration definition
     */
    const JWK_URI = 'https://www.gstatic.com/iap/verify/public_key-jwk';

    /**
     * JWT Signature Algorithm
     */
    const JWT_SIG_ALG = 'ES256';

    /**
     * List of acceptable JWT issuers
     */
    const JWT_ISSUERS = [
        'https://cloud.google.com/iap'
    ];

    /**
     * Validate an IAP ID token.
     *
     * @param string $iap_jwt The JWT token to be validated.
     * @param string $expected_audience The expected audience of the provided JWT.
     * 
     * @throws \A1comms\GaeSupportLaravel\Auth\Exception\InvalidTokenException if the token is invalid.
     * 
     * @return array Returns array containing "sub" and "email" if token is valid.
     */
    public static function validateToken($iap_jwt, $expected_audience)
    {
        $jwk_url = self::get_jwk_url();

        return JWT::validate($iap_jwt, $expected_audience, $jwk_url, self::JWT_SIG_ALG, self::JWT_ISSUERS);
    }

    /**
     * The full uri for accessing the public token signing keys (JWK).
     *
     * @return string
     */
    protected static function get_jwk_url()
    {
        return self::JWK_URI;
    }
}