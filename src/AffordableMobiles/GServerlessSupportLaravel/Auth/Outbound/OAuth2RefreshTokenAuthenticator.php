<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Token\TokenCache;

class OAuth2RefreshTokenAuthenticator extends AbstractOAuth2Authenticator
{
    /**
     * @param callable|string $refreshToken     Refresh token string or callable resolving it
     * @param null|callable   $onTokenRefreshed Callback triggered when token is refreshed, useful for rotated refresh tokens
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
            fallbackTtl: $fallbackTtl,
            bufferSeconds: $bufferSeconds,
        );
    }

    protected function getCacheKey(): string
    {
        $token = \is_callable($this->refreshToken) ? 'dynamic' : hash('sha256', (string) $this->refreshToken);

        return TokenCache::key([
            'driver'             => 'refresh_token',
            'token_url'          => $this->tokenUrl,
            'client_id'          => $this->clientId,
            'refresh_token_hash' => $token,
            'scope'              => $this->scope ?? '',
        ]);
    }

    protected function fetchToken(): array
    {
        $refreshTokenValue = \is_callable($this->refreshToken)
            ? (string) ($this->refreshToken)()
            : (string) $this->refreshToken;

        $params = array_merge([
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshTokenValue,
        ], $this->extraTokenParams);

        if (null !== $this->scope && '' !== $this->scope) {
            $params['scope'] = $this->scope;
        }

        $options = [];

        if ('client_secret_basic' === $this->authMethod) {
            $options['auth']        = [$this->clientId, $this->clientSecret];
            $options['form_params'] = $params;
        } else {
            $params['client_id']     = $this->clientId;
            $params['client_secret'] = $this->clientSecret;
            $options['form_params']  = $params;
        }

        $data = $this->executeTokenRequest($options);

        if (null !== $this->onTokenRefreshed && \is_callable($this->onTokenRefreshed)) {
            ($this->onTokenRefreshed)($data);
        }

        return [
            'token'      => (string) $data['access_token'],
            'expires_in' => (int) ($data['expires_in'] ?? $this->fallbackTtl),
            'raw'        => $data,
        ];
    }
}
