<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Token\TokenCache;

class OAuth2PasswordAuthenticator extends AbstractOAuth2Authenticator
{
    public function __construct(
        string $tokenUrl,
        protected readonly string $clientId,
        protected readonly string $clientSecret,
        protected readonly string $username,
        protected readonly string $password,
        protected readonly ?string $scope = null,
        protected readonly string $authMethod = 'client_secret_basic',
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
        return TokenCache::key([
            'driver'    => 'password',
            'token_url' => $this->tokenUrl,
            'client_id' => $this->clientId,
            'user_hash' => hash('sha256', $this->username),
            'scope'     => $this->scope ?? '',
        ]);
    }

    protected function fetchToken(): array
    {
        $params = array_merge([
            'grant_type' => 'password',
            'username'   => $this->username,
            'password'   => $this->password,
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

        return [
            'token'      => (string) $data['access_token'],
            'expires_in' => (int) ($data['expires_in'] ?? $this->fallbackTtl),
            'raw'        => $data,
        ];
    }
}
