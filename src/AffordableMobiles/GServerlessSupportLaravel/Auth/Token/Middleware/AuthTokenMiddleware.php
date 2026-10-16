<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Token\Middleware;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Contracts\RequestAuthenticatorInterface;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound\AbstractOAuth2Authenticator;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound\GoogleOAuth2Authenticator;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound\GoogleOidcAuthenticator;
use AffordableMobiles\GServerlessSupportLaravel\Auth\OutboundAuth;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Token\OAuth2;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Token\OIDC;
use AffordableMobiles\GServerlessSupportLaravel\Support\CertificateMaterializer;
use AffordableMobiles\GServerlessSupportLaravel\Support\UriMatcher;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * AuthTokenMiddleware is a Guzzle Middleware that adds outbound authentication
 * to requests, supporting Google IAP/OIDC, Google OAuth2, OAuth 2.0 (RFC 6749 / RFC 7523),
 * OAuth 1.0a TBA (RFC 5849), and custom API keys.
 */
class AuthTokenMiddleware
{
    /**
     * @var null|callable
     */
    private $audienceSource;

    /**
     * @var null|callable
     */
    private $tokenTypeSource;

    /**
     * @var array<string,mixed>
     */
    private array $audienceMap = [];

    /**
     * @param null|callable       $audienceSource  (optional) callable to return target_audience for OIDC
     * @param null|callable       $tokenTypeSource (optional) callable to return token_type
     * @param array<string,mixed> $audienceMap     (optional) full audience / outbound auth mapping array
     */
    public function __construct(
        ?callable $audienceSource = null,
        ?callable $tokenTypeSource = null,
        array $audienceMap = [],
    ) {
        $this->audienceSource  = $audienceSource;
        $this->tokenTypeSource = $tokenTypeSource;
        $this->audienceMap     = $audienceMap;
    }

    /**
     * @return \Closure
     */
    public function __invoke(callable $handler)
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $authenticator = $this->resolveAuthenticator($request, $options);

            if (null !== $authenticator) {
                $request = $authenticator->authenticate($request, $options);
            }

            /** @var PromiseInterface $promise */
            $promise = $handler($request, $options);

            // Handle automatic single re-auth retry on 401 Unauthorized for cached OAuth2 tokens
            if ($authenticator instanceof AbstractOAuth2Authenticator && empty($options['_auth_retried'])) {
                return $promise->then(
                    function (ResponseInterface $response) use ($handler, $request, $options, $authenticator) {
                        if (401 === $response->getStatusCode()) {
                            return $this->retryWithFreshToken($handler, $request, $options, $authenticator);
                        }

                        return $response;
                    },
                    function (\Throwable $reason) use ($handler, $request, $options, $authenticator) {
                        if ($reason instanceof BadResponseException && 401 === $reason->getResponse()->getStatusCode()) {
                            return $this->retryWithFreshToken($handler, $request, $options, $authenticator);
                        }

                        throw $reason;
                    }
                );
            }

            return $promise;
        };
    }

    /**
     * Factory creating a configured AuthTokenMiddleware instance from configuration.
     *
     * @param array<string,mixed> $audienceMap
     */
    public static function factory(array $audienceMap = []): self
    {
        if (empty($audienceMap)) {
            try {
                $location    = config('gserverlesssupport.auth.middleware.audience_map_location', 'auth.middleware.audience');
                $audienceMap = (array) config($location, []);
            } catch (\Throwable) {
                $audienceMap = [];
            }
        }

        $audienceSource = static function (UriInterface $request_uri) use ($audienceMap) {
            $matched = UriMatcher::match($audienceMap, $request_uri);

            if (\is_array($matched)) {
                $matchedAudience = $matched['audience'] ?? $matched['target_audience'] ?? null;
                if (null !== $matchedAudience) {
                    return $matchedAudience;
                }
            } elseif (\is_string($matched) && '' !== $matched) {
                return $matched;
            }

            if (self::isCloudFunction($request_uri)) {
                return \sprintf(
                    '%s://%s%s',
                    $request_uri->getScheme(),
                    $request_uri->getHost(),
                    $request_uri->getPath(),
                );
            }

            return null;
        };

        $tokenTypeSource = static function (UriInterface $request_uri) use ($audienceMap) {
            $matched = UriMatcher::match($audienceMap, $request_uri);

            if (\is_array($matched)) {
                if (isset($matched['token_type'])) {
                    return $matched['token_type'];
                }

                $matchedAudience = $matched['audience'] ?? $matched['target_audience'] ?? null;
                if ('oauth2' === $matchedAudience) {
                    return 'oauth2';
                }

                return null !== $matchedAudience ? 'oidc' : false;
            }

            if ('oauth2' === $matched) {
                return 'oauth2';
            }

            if (\is_string($matched) && '' !== $matched) {
                return 'oidc';
            }

            if (self::isCloudFunction($request_uri)) {
                return 'oidc';
            }

            return false;
        };

        return new static($audienceSource, $tokenTypeSource, $audienceMap);
    }

    /**
     * Resolve the appropriate authenticator for the outbound request,
     * and apply any matched transport options (proxy, verify, mTLS).
     *
     * @param array<string,mixed> $options
     */
    protected function resolveAuthenticator(RequestInterface $request, array &$options): ?RequestAuthenticatorInterface
    {
        $auth = $options['outbound_auth'] ?? $options['auth'] ?? 'google_dynamic';

        // 1. Explicit RequestAuthenticatorInterface passed in options
        if ($auth instanceof RequestAuthenticatorInterface) {
            return $auth;
        }

        // 2. Explicit config array passed in options
        if (\is_array($auth)) {
            $this->applyTransportOptions($auth, $options);

            return OutboundAuth::fromConfig($auth);
        }

        // 3. Explicit named driver strings
        if ('google_oidc' === $auth) {
            $audience = $this->fetchOIDCTargetAudience($request->getUri());

            return '' !== $audience ? new GoogleOidcAuthenticator($audience) : null;
        }

        if ('google_oauth2' === $auth) {
            return new GoogleOAuth2Authenticator();
        }

        // 4. Default dynamic resolution via request map, audience map & UriMatcher
        if ('google_dynamic' === $auth) {
            // Check request-scoped runtime map first (e.g. registered by Route Middleware or ConfigController)
            $requestMap = OutboundAuth::getRequestMap();
            if (!empty($requestMap)) {
                $matched = UriMatcher::match($requestMap, $request->getUri());
                if (null !== $matched) {
                    if (\is_array($matched)) {
                        $this->applyTransportOptions($matched, $options);
                    }

                    return OutboundAuth::fromConfig($matched);
                }
            }

            // Fallback to configured global audience / outbound auth map
            $matched = UriMatcher::match($this->audienceMap, $request->getUri());

            if (null !== $matched) {
                if (\is_array($matched)) {
                    $this->applyTransportOptions($matched, $options);
                }

                return OutboundAuth::fromConfig($matched);
            }

            if (null !== $this->tokenTypeSource && null !== $this->audienceSource) {
                $token = $this->fetchDynamicToken($request->getUri());
                if (!empty($token)) {
                    return new class($token) implements RequestAuthenticatorInterface {
                        public function __construct(protected readonly string $token) {}

                        public function authenticate(RequestInterface $request, array &$options = []): RequestInterface
                        {
                            return $request->withHeader('authorization', 'Bearer '.$this->token);
                        }
                    };
                }
            }

            if (self::isCloudFunction($request->getUri())) {
                $audience = \sprintf(
                    '%s://%s%s',
                    $request->getUri()->getScheme(),
                    $request->getUri()->getHost(),
                    $request->getUri()->getPath(),
                );

                return new GoogleOidcAuthenticator($audience);
            }
        }

        return null;
    }

    /**
     * Apply transport options (proxy, verify, mTLS cert & key) from matched host configuration.
     *
     * @param array<string,mixed> $config
     * @param array<string,mixed> $options
     */
    protected function applyTransportOptions(array $config, array &$options): void
    {
        if (!isset($options['proxy']) && !empty($config['proxy'])) {
            $options['proxy'] = $config['proxy'];
        }

        if ((!isset($options['verify']) || true === $options['verify']) && !empty($config['verify'])) {
            $options['verify'] = $config['verify'];
        }

        $mtls = $config['mtls'] ?? $config;

        if (!empty($mtls['cert'])) {
            $inMemory = (bool) ($mtls['in_memory'] ?? true);
            CertificateMaterializer::bindToRequestOptions(
                $options,
                $mtls['cert'],
                $mtls['key']           ?? $mtls['ssl_key'] ?? null,
                $mtls['cert_password'] ?? $mtls['passphrase'] ?? null,
                $mtls['key_password']  ?? null,
                $inMemory,
            );
        }
    }

    /**
     * Invalidate cached token and retry request once.
     *
     * @param array<string,mixed> $options
     */
    protected function retryWithFreshToken(
        callable $handler,
        RequestInterface $request,
        array $options,
        AbstractOAuth2Authenticator $authenticator,
    ): PromiseInterface {
        $authenticator->invalidateToken();

        $retryOptions                  = $options;
        $retryOptions['_auth_retried'] = true;

        if ($request->getBody()->isSeekable()) {
            $request->getBody()->rewind();
        }

        $freshRequest = $authenticator->authenticate($request, $retryOptions);

        return $handler($freshRequest, $retryOptions);
    }

    /**
     * Call dynamic handler to fetch legacy token.
     */
    protected function fetchDynamicToken(UriInterface $request_uri): ?string
    {
        if (null === $this->tokenTypeSource) {
            return null;
        }

        $tokenType = \call_user_func($this->tokenTypeSource, $request_uri);

        return match ($tokenType) {
            'oidc'   => $this->fetchOIDCToken($request_uri),
            'oauth2' => $this->fetchOAuth2Token($request_uri),
            default  => null,
        };
    }

    /**
     * Call OIDC handler to fetch token.
     */
    protected function fetchOIDCToken(UriInterface $request_uri): string
    {
        $targetAudience = $this->fetchOIDCTargetAudience($request_uri);

        return OIDC::fetchToken($targetAudience);
    }

    /**
     * Resolve target audience string for URI.
     */
    protected function fetchOIDCTargetAudience(UriInterface $request_uri): string
    {
        if (null !== $this->audienceSource) {
            return (string) \call_user_func($this->audienceSource, $request_uri);
        }

        return '';
    }

    /**
     * Call OAuth2 handler to fetch token.
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
