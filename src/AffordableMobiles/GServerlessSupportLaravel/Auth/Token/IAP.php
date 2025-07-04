<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Token;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Exception\InvalidTokenException;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Token\Type\JWT;
use Illuminate\Support\Arr;

class IAP
{
    /**
     * URI of the public OpenID configuration definition.
     */
    public const JWK_URI = 'https://www.gstatic.com/iap/verify/public_key-jwk';

    /**
     * JWT Signature Algorithm.
     */
    public const JWT_SIG_ALG = 'ES256';

    /**
     * List of acceptable JWT issuers.
     */
    public const JWT_ISSUERS = [
        'https://cloud.google.com/iap',
    ];

    /**
     * Validate an IAP ID token and check for a required access level.
     *
     * This method cryptographically verifies the JWT. On success, it returns the
     * claims as an array, with the 'google' claim enriched into a `GoogleClaim` object.
     *
     * @param string      $iap_jwt               the JWT token to be validated
     * @param string      $expected_audience     the expected audience of the provided JWT
     * @param null|string $required_access_level the full name of the required Endpoint Verification access level
     *
     * @return array the validated and enriched claims array
     *
     * @throws InvalidTokenException if the token is invalid or doesn't meet security requirements
     */
    public static function validateToken(string $iap_jwt, string $expected_audience, ?string $required_access_level = null): array
    {
        // Perform base cryptographic validation and check standard claims.
        $claims = JWT::validate($iap_jwt, $expected_audience, self::get_jwk_url(), self::JWT_SIG_ALG, self::JWT_ISSUERS);

        // Enrich the 'google' claim into a structured object.
        $googleClaim      = new Claims\Google(Arr::get($claims, 'google', []));
        $claims['google'] = $googleClaim;

        // If an access level is required, perform the Endpoint Verification check.
        if ($required_access_level) {
            if (!$googleClaim->hasAccessLevel($required_access_level)) {
                throw new InvalidTokenException(
                    'JWT is valid, but is missing the required access level for Endpoint Verification.'
                );
            }
        }

        return $claims;
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
