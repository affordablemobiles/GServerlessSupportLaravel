<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Token\Middleware;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Token\OAuth2;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Token\OIDC;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * AuthTokenMiddleware is a Guzzle Middleware that adds an Authorization header
 * providing either an OIDC "id_token" or OAuth2 "access_token".
 *
 * Requests will be accessed with the authorization header:
 *
 * 'authorization' 'Bearer <value of auth_token>'
 */
class AuthTokenMiddleware
{
    /**
     * @var callable
     */
    private $audienceSource;

    /**
     * @var callable
     */
    private $tokenTypeSource;

    /**
     * Creates a new AuthTokenMiddleware.
     *
     * @param callable $audienceSource (optional) function to be called to return the target_audience for OIDC
     */
    public function __construct(?callable $audienceSource = null, ?callable $tokenTypeSource = null)
    {
        $this->audienceSource  = $audienceSource;
        $this->tokenTypeSource = $tokenTypeSource;
    }

    /**
     * Updates the request with an Authorization header when auth is 'google_auth'.
     *
     *   use GuzzleHttp\Client;
     *   use GuzzleHttp\HandlerStack;
     *   use AffordableMobiles\GServerlessSupportLaravel\Auth\Token\Middleware\AuthTokenMiddleware;
     *
     *   $audienceSource = function(\Psr\Http\Message\UriInterface $request_uri) {
     *       return "my-client-id@gcloud.internal";
     *   }
     *
     *   $authMiddleware = new AuthTokenMiddleware($audienceSource);
     *   $stack = HandlerStack::create();
     *   $stack->push($authMiddleware);
     *
     *   $client = new Client([
     *       'handler' => $stack,
     *        // authorize all requests,
     *        // "google_oidc" or "google_oauth2"
     *       'auth' => 'google_oidc'
     *   ]);
     *
     *   $res = $client->get('https://my-super-secure-app.appspot.com/tasks/list');
     *
     * @return \Closure
     */
    public function __invoke(callable $handler)
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $authType = $options['auth'] ?? 'google_dynamic';

            switch ($authType) {
                case 'google_oidc':
                    $request = $request->withHeader('authorization', 'Bearer '.$this->fetchOIDCToken(
                        $request->getUri()
                    ));

                    break;

                case 'google_oauth2':
                    $request = $request->withHeader('authorization', 'Bearer '.$this->fetchOAuth2Token(
                        $request->getUri()
                    ));

                    break;

                case 'google_dynamic':
                    $token = $this->fetchDynamicToken(
                        $request->getUri()
                    );
                    if (!empty($token)) {
                        $request = $request->withHeader('authorization', 'Bearer '.$token);
                    }

                    break;

                default:
                    break;
            }

            return $handler($request, $options);
        };
    }

    /**
     * Provides a closure that can be pushed onto the handler stack,
     *  with a default, reusable implementation.
     *
     * Example:
     * <code>$handlerStack->push(AuthTokenMiddleware::factory());</code>
     *
     * @param array<string,string> $audienceMap
     */
    public static function factory(array $audienceMap = []): self
    {
        if (empty($audienceMap)) {
            $audienceMap = config(
                config('gserverlesssupport.auth.middleware.audience_map_location'),
                [],
            );
        }

        $audienceSource = static function (UriInterface $request_uri) use ($audienceMap) {
            if (self::isCloudFunction($request_uri)) {
                return \sprintf(
                    '%s://%s%s',
                    $request_uri->getScheme(),
                    $request_uri->getHost(),
                    $request_uri->getPath(),
                );
            }

            return $audienceMap[$request_uri->getHost()];
        };

        $tokenTypeSource = static function (UriInterface $request_uri) use ($audienceMap) {
            if (self::isCloudFunction($request_uri)) {
                return 'oidc';
            }

            $audience = $audienceMap[$request_uri->getHost()] ?? false;

            if ('oauth2' === $audience) {
                return 'oauth2';
            }

            return false !== $audience ? 'oidc' : false;
        };

        return new static($audienceSource, $tokenTypeSource);
    }

    /**
     * Call dynamic handler to fetch the token.
     */
    protected function fetchDynamicToken(UriInterface $request_uri): ?string
    {
        $tokenType = \call_user_func($this->tokenTypeSource, $request_uri);

        switch ($tokenType) {
            case 'oidc':
                return $this->fetchOIDCToken($request_uri);

            case 'oauth2':
                return $this->fetchOAuth2Token($request_uri);

            default:
                return null;
        }
    }

    /**
     * Call OIDC handler to fetch the token.
     */
    protected function fetchOIDCToken(UriInterface $request_uri): string
    {
        $target_audience = \call_user_func($this->audienceSource, $request_uri);

        return OIDC::fetchToken($target_audience);
    }

    /**
     * Call OAuth2 handler to fetch the token.
     */
    protected function fetchOAuth2Token(UriInterface $request_uri): string
    {
        return OAuth2::fetchToken();
    }

    /**
     * Does the URL belong to Google Cloud Functions (GCF)?
     */
    protected static function isCloudFunction(UriInterface $request_uri): bool
    {
        return str_ends_with($request_uri->getHost(), '.cloudfunctions.net');
    }
}
