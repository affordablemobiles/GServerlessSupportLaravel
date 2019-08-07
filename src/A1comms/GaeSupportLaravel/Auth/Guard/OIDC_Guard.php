<?php

namespace A1comms\GaeSupportLaravel\Auth\Guard;

use GuzzleHttp\Client;
use SimpleJWT\JWT;
use SimpleJWT\Keys\KeySet;
use SimpleJWT\InvalidTokenException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Auth\UserProvider;
use A1comms\GaeSupportLaravel\Cache\InstanceLocal as InstanceLocalCache;

class OIDC_Guard extends BaseGuard
{
    /**
     * Authenticate a user based on request information,
     * return a valid user object if successful, or null.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public static function validate(Request $request, UserProvider $provider = null)
    {
        $expected_audience = env('OIDC_AUDIENCE');
        if (empty($expected_audience)) {
            throw new Exception("OIDC Authentication Guard: Audience (env OIDC_AUDIENCE) not defined");
        }

        $jwt = $request->bearerToken();
        if (empty($jwt)) {
            return null;
        }

        try {
            $return = self::validate_jwt($jwt, $expected_audience);
        } catch (InvalidTokenException $e) {
            Log::warning('OIDC Authentication Guard: ' . $e->getMessage());
            
            return null;
        }

        return static::returnUser($provider, $return['email']);
    }

    public static function validate_jwt($oidc_jwt, $expected_audience)
    {
        $jwkset = self::get_jwk_set();

        // Validate the signature using the key set and RS256 algorithm.
        $jwt = JWT::decode($oidc_jwt, $jwkset, 'RS256');

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

    public static function get_jwk_set()
    {
        // Create a JWK Key Set from the gstatic URL
        $jwkset = new KeySet();
        $jwkset->load(self::get_jwk_set_raw());

        return $jwkset;
    }

    protected static function get_jwk_set_raw()
    {
        // get the public key JWK Set object (RFC7517)
        return InstanceLocalCache::remember('google_oidc_jwk_set', 3600, function () {
            $httpclient = new Client();
            $response = $httpclient->request('GET', self::get_jwk_url(), []);

            return ((string) $response->getBody());
        });
    }

    protected static function get_jwk_url()
    {
        $httpclient = new Client();
        $response = $httpclient->request('GET', 'https://accounts.google.com/.well-known/openid-configuration', []);

        $result = json_decode((string) $response->getBody(), true);

        return $result['jwks_uri'];
    }
}