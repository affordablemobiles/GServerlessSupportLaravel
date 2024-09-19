<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Auth\Token;

use A1comms\GaeSupportLaravel\Auth\Exception\InvalidTokenException;
use A1comms\GaeSupportLaravel\Auth\Token\Type\JWT_x509;
use A1comms\GaeSupportLaravel\Integration\Guzzle\Tools as GuzzleTools;
use Google\Auth\Credentials\GCECredentials;
use Google\Auth\Middleware\AuthTokenMiddleware;
use Google\Cloud\Core\ExponentialBackoff;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

class Firebase
{
    /**
     * URI of the public OpenID configuration definition.
     */
    public const JWK_URI = 'https://www.googleapis.com/identitytoolkit/v3/relyingparty/publicKeys';

    /**
     * JWT Signature Algorithm.
     */
    public const JWT_SIG_ALG = 'RS256';

    /**
     * Connection timeout for the request.
     */
    public const REQUEST_CONNECTION_TIMEOUT_S = 0.5;

    /**
     * Timeout for the whole request.
     */
    public const REQUEST_TIMEOUT_S = 1;

    /**
     * Validate a Firebase session cookie token.
     *
     * @param string $sessionCookie_jwt the JWT token to be validated
     * @param string $expected_audience the expected audience of the provided JWT (project id)
     *
     * @return array returns array containing "sub" and "email" if token is valid
     *
     * @throws InvalidTokenException if the token is invalid
     */
    public static function validateToken($sessionCookie_jwt, $expected_audience)
    {
        $jwk_url = self::get_jwk_url();

        return JWT_x509::validate($sessionCookie_jwt, $expected_audience, $jwk_url, self::JWT_SIG_ALG, [
            'https://session.firebase.google.com/'.$expected_audience,
        ]);
    }

    public static function userLookup($expected_audience, $idToken, $localId, $tenantId = null)
    {
        $stack = HandlerStack::create();
        $stack->push(
            new AuthTokenMiddleware(
                new GCECredentials()
            )
        );

        $client = new Client([
            'handler' => $stack,
            'auth'    => 'google_auth',
        ]);

        $data = [
            'idToken'       => $idToken,
            'localId'       => $localId,
        ];
        if (!empty($tenantId)) {
            $data['tenantId'] = $tenantId;
        }

        try {
            $response = (new ExponentialBackoff(6, [self::class, 'shouldRetry']))->execute([$client, 'request'], [
                'POST',
                'https://identitytoolkit.googleapis.com/v1/projects/'.$expected_audience.'/accounts:lookup',
                [
                    'json'            => $data,
                    'http_errors'     => true,
                    'connect_timeout' => self::REQUEST_CONNECTION_TIMEOUT_S,
                    'timeout'         => self::REQUEST_TIMEOUT_S,
                ],
            ]);
        } catch (GuzzleException $e) {
            throw $e;
        }

        if (200 !== $response->getStatusCode()) {
            $fallbackMessage = 'Failed to sign in';
            \Log::info('response body', [$response->getBody()]);

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

        return $resp_data;
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
            'auth'    => 'google_auth',
        ]);

        $data = [
            'idToken'       => $idToken,
            'validDuration' => $expiry,
        ];
        if (!empty($tenantId)) {
            $data['tenantId'] = $tenantId;
        }

        try {
            $response = (new ExponentialBackoff(6, [self::class, 'shouldRetry']))->execute([$client, 'request'], [
                'POST',
                'https://identitytoolkit.googleapis.com/v1/projects/'.$expected_audience.':createSessionCookie',
                [
                    'json'            => $data,
                    'http_errors'     => false,
                    'connect_timeout' => self::REQUEST_CONNECTION_TIMEOUT_S,
                    'timeout'         => self::REQUEST_TIMEOUT_S,
                ],
            ]);
        } catch (GuzzleException $e) {
            throw $e;
        }

        if (200 !== $response->getStatusCode()) {
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

    public static function shouldRetry($ex, $retryAttempt = 1)
    {
        if (GuzzleTools::isConnectionError($ex, self::REQUEST_CONNECTION_TIMEOUT_S)) {
            return true;
        }

        return false;
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
