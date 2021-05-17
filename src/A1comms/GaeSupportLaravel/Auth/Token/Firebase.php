<?php

namespace A1comms\GaeSupportLaravel\Auth\Token;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Google\Auth\Credentials\GCECredentials;
use Google\Auth\Middleware\AuthTokenMiddleware;
use A1comms\GaeSupportLaravel\Auth\Token\Type\JWT_x509;
use A1comms\GaeSupportLaravel\Auth\Exception\InvalidTokenException;

class Firebase
{
    /**
     * URI of the public OpenID configuration definition
     */
    const JWK_URI = 'https://www.googleapis.com/identitytoolkit/v3/relyingparty/publicKeys';

    /**
     * JWT Signature Algorithm
     */
    const JWT_SIG_ALG = 'RS256';

    /**
     * Validate a Firebase session cookie token.
     *
     * @param string $sessionCookie_jwt The JWT token to be validated.
     * @param string $expected_audience The expected audience of the provided JWT (project id).
     *
     * @throws \A1comms\GaeSupportLaravel\Auth\Exception\InvalidTokenException if the token is invalid.
     *
     * @return array Returns array containing "sub" and "email" if token is valid.
     */
    public static function validateToken($sessionCookie_jwt, $expected_audience)
    {
        $jwk_url = self::get_jwk_url();

        return JWT_x509::validate($sessionCookie_jwt, $expected_audience, $jwk_url, self::JWT_SIG_ALG, [
            'https://session.firebase.google.com/' . $expected_audience,
        ]);
    }

    public static function fetchToken($expected_audience, $idToken, $expiry = (3600 * 24 * 7), $tenantId = null)
    {
        $stack = HandlerStack::create();
        $stack->push(
            new AuthTokenMiddleware(
                new GCECredentials()
            )
        );

        $client = new Client([
            'handler' => $stack,
            'auth' => 'google_auth',
        ]);

        $data = [
            'idToken' => $idToken,
            'validDuration' => $expiry,
        ];
        if (!empty($tenantId)) {
            $data['tenantId'] = $tenantId;
        }

        try {
            $response = $client->request(
                'POST',
                'https://identitytoolkit.googleapis.com/v1/projects/' . $expected_audience . ':createSessionCookie',
                [
                    'json' => $data,
                    'http_errors' => false,
                ]
            );
        } catch (GuzzleException $e) {
            throw $e;
        }

        if ($response->getStatusCode() !== 200) {
            $fallbackMessage = 'Failed to sign in';

            try {
                $message = json_decode((string) $response->getBody(), true)['error']['message'] ?? $fallbackMessage;
            } catch (InvalidArgumentException $e) {
                $message = $fallbackMessage;
            }

            throw new \Exception($message);
        }

        try {
            $resp_data = json_decode((string) $response->getBody(), true);
        } catch (\InvalidArgumentException $e) {
            throw new \Exception('failed to sign in: invalid response');
        }

        return $resp_data['sessionCookie'];
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
