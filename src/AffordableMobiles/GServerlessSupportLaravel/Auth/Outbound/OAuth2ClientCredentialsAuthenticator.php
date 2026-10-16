<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Token\TokenCache;

class OAuth2ClientCredentialsAuthenticator extends AbstractOAuth2Authenticator
{
    /**
     * @param string               $tokenUrl             Token endpoint URL
     * @param string               $clientId             OAuth client ID
     * @param string               $clientSecret         OAuth client secret
     * @param null|string          $scope                Optional scope
     * @param null|string          $audience             Optional audience
     * @param string               $authMethod           Authentication method: 'client_secret_basic', 'client_secret_post', or 'client_secret_json'
     * @param array<string,mixed>  $extraTokenParams     Additional parameters to include in token request
     * @param array<string,string> $extraResourceHeaders Additional headers to include in resource request
     */
    public function __construct(
        string $tokenUrl,
        protected readonly string $clientId,
        protected readonly string $clientSecret,
        protected readonly ?string $scope = null,
        protected readonly ?string $audience = null,
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
            'driver'    => 'client_credentials',
            'token_url' => $this->tokenUrl,
            'client_id' => $this->clientId,
            'scope'     => $this->scope    ?? '',
            'audience'  => $this->audience ?? '',
        ]);
    }

    protected function fetchToken(): array
    {
        $params = array_merge([
            'grant_type' => 'client_credentials',
        ], $this->extraTokenParams);

        if (null !== $this->scope && '' !== $this->scope) {
            $params['scope'] = $this->scope;
        }

        if (null !== $this->audience && '' !== $this->audience) {
            $params['audience'] = $this->audience;
        }

        $options = [];

        if ('client_secret_basic' === $this->authMethod) {
            $options['auth']        = [$this->clientId, $this->clientSecret];
            $options['form_params'] = $params;
        } elseif ('client_secret_json' === $this->authMethod) {
            $params['client_id']     = $this->clientId;
            $params['client_secret'] = $this->clientSecret;
            $options['json']         = $params;
        } else {
            // client_secret_post
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
