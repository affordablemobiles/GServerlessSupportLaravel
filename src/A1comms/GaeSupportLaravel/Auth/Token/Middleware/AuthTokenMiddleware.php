<?php

namespace A1comms\GaeSupportLaravel\Auth\Token\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use A1comms\GaeSupportLaravel\Auth\Token\OIDC;
use A1comms\GaeSupportLaravel\Auth\Token\OAuth2;

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
     * Creates a new AuthTokenMiddleware.
     *
     * @param callable $audienceSource (optional) function to be called to return the target_audience for OIDC.
     */
    public function __construct(callable $audienceSource = null) {
        $this->audienceSource = $audienceSource;
    }

    /**
     * Updates the request with an Authorization header when auth is 'google_auth'.
     *
     *   use GuzzleHttp\Client;
     *   use GuzzleHttp\HandlerStack;
     *   use A1comms\GaeSupportLaravel\Auth\Token\Middleware\AuthTokenMiddleware;
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
     * @param callable $handler
     *
     * @return \Closure
     */
    public function __invoke(callable $handler)
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            if (!empty($options['auth'])) {
                switch ($options['auth']) {
                    case 'google_oidc':
                        $request = $request->withHeader('authorization', 'Bearer ' . $this->fetchOIDCToken(
                            $request->getUri()
                        ));
                        break;
                    case 'google_oauth2':
                        $request = $request->withHeader('authorization', 'Bearer ' . $this->fetchOAuth2Token());
                        break;
                    default:
                        break;
                }
            }

            return $handler($request, $options);
        };
    }

    /**
     * Call OIDC handler to fetch the token.
     *
     * @return string
     */
    protected function fetchOIDCToken(UriInterface $request_uri)
    {
        $target_audience = call_user_func($this->audienceSource, $request_uri);
        
        return OIDC::fetchToken($target_audience);
    }

    /**
     * Call OAuth2 handler to fetch the token.
     *
     * @return string
     */
    protected function fetchOAuth2Token(UriInterface $request_uri)
    {
        return OAuth2::fetchToken();
    }
}