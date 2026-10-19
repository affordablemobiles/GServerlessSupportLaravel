<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Token\TokenCache;

class OAuth2RefreshTokenAuthenticator extends AbstractOAuth2Authenticator
{
    /**
     * @param mixed                $refreshToken         Refresh token string, invokable class string, or callable
     * @param null|string          $scope                Optional scope
     * @param string               $authMethod           Authentication method: 'client_secret_basic' or 'client_secret_post'
     * @param mixed                $onTokenRefreshed     Callback, invokable class string, or [Class, method]
     * @param array<string,mixed>  $extraTokenParams     Additional parameters for token request
     * @param array<string,string> $extraResourceHeaders Additional headers for resource request
     * @param array<string,mixed>  $tokenClientOptions   Custom Guzzle options for token endpoint (proxy, verify, etc.)
     * @param string               $tokenResponseKey     JSON key or dot-notation path to extract token from (default 'access_token')
     */
    public function __construct(
        string $tokenUrl,
        protected readonly string $clientId,
        protected readonly string $clientSecret,
        protected readonly mixed $refreshToken,
        protected readonly ?string $scope = null,
        protected readonly string $authMethod = 'client_secret_basic',
        protected readonly mixed $onTokenRefreshed = null,
        protected readonly array $extraTokenParams = [],
        array $extraResourceHeaders = [],
        array $tokenClientOptions = [],
        string $tokenResponseKey = 'access_token',
        string $headerName = 'Authorization',
        string $tokenType = 'Bearer',
        int $fallbackTtl = self::DEFAULT_FALLBACK_TTL,
        int $bufferSeconds = self::DEFAULT_BUFFER_SECONDS,
    ) {
        parent::__construct(
            tokenUrl: $tokenUrl,
            headerName: $headerName,
            tokenType: $tokenType,
            extraResourceHeaders: $extraResourceHeaders,
            tokenClientOptions: $tokenClientOptions,
            tokenResponseKey: $tokenResponseKey,
            fallbackTtl: $fallbackTtl,
            bufferSeconds: $bufferSeconds,
        );
    }

    protected function getCacheKey(): string
    {
        $isDynamic = null !== $this->resolveCallable($this->refreshToken);
        $token     = $isDynamic ? 'dynamic' : hash('sha256', (string) $this->refreshToken);

        return TokenCache::key([
            'driver'             => 'refresh_token',
            'token_url'          => $this->tokenUrl,
            'client_id'          => $this->clientId,
            'refresh_token_hash' => $token,
            'scope'              => $this->scope ?? '',
        ]);
    }

    protected function fetchToken(array $options = []): array
    {
        $refreshTokenCallable = $this->resolveCallable($this->refreshToken);
        $refreshTokenValue    = null !== $refreshTokenCallable
            ? (string) ($refreshTokenCallable)()
            : (string) $this->refreshToken;

        $params = array_merge([
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshTokenValue,
        ], $this->extraTokenParams);

        if (null !== $this->scope && '' !== $this->scope) {
            $params['scope'] = $this->scope;
        }

        $requestOptions = [];

        if ('client_secret_basic' === $this->authMethod) {
            $requestOptions['auth']        = [$this->clientId, $this->clientSecret];
            $requestOptions['form_params'] = $params;
        } else {
            $params['client_id']           = $this->clientId;
            $params['client_secret']       = $this->clientSecret;
            $requestOptions['form_params'] = $params;
        }

        $data = $this->executeTokenRequest($requestOptions, $options);

        $onRefreshed = $this->resolveCallable($this->onTokenRefreshed);
        if (null !== $onRefreshed) {
            ($onRefreshed)($data);
        }

        $token = data_get($data, $this->tokenResponseKey);

        return [
            'token'      => (string) $token,
            'expires_in' => (int) ($data['expires_in'] ?? $this->fallbackTtl),
            'raw'        => $data,
        ];
    }

    /**
     * Resolve a callable or invokable class string.
     */
    protected function resolveCallable(mixed $target): ?callable
    {
        if (null === $target) {
            return null;
        }

        if (\is_callable($target)) {
            return $target;
        }

        if (\is_string($target) && class_exists($target)) {
            try {
                $instance = app($target);
                if (\is_callable($instance)) {
                    return $instance;
                }
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
