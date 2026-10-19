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
     * @param string               $authMethod           Authentication method: 'client_secret_basic', 'client_secret_post', 'client_secret_json', or 'client_secret_query'
     * @param array<string,mixed>  $extraTokenParams     Additional parameters to include in token request
     * @param array<string,string> $extraResourceHeaders Additional headers to include in resource request
     * @param array<string,mixed>  $tokenClientOptions   Custom Guzzle options for token endpoint (proxy, verify, etc.)
     * @param string               $tokenResponseKey     JSON key or dot-notation path to extract token from (default 'access_token')
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
        return TokenCache::key([
            'driver'    => 'client_credentials',
            'token_url' => $this->tokenUrl,
            'client_id' => $this->clientId,
            'scope'     => $this->scope    ?? '',
            'audience'  => $this->audience ?? '',
        ]);
    }

    protected function fetchToken(array $options = []): array
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

        $requestOptions = [];

        if ('client_secret_basic' === $this->authMethod) {
            $requestOptions['auth']        = [$this->clientId, $this->clientSecret];
            $requestOptions['form_params'] = $params;
        } elseif ('client_secret_json' === $this->authMethod) {
            $params['client_id']           = $this->clientId;
            $params['client_secret']       = $this->clientSecret;
            $requestOptions['json']        = $params;
        } elseif ('client_secret_query' === $this->authMethod) {
            $params['client_id']           = $this->clientId;
            $params['client_secret']       = $this->clientSecret;
            $requestOptions['query']       = $params;
        } else {
            // client_secret_post
            $params['client_id']           = $this->clientId;
            $params['client_secret']       = $this->clientSecret;
            $requestOptions['form_params'] = $params;
        }

        $data = $this->executeTokenRequest($requestOptions, $options);

        $token = data_get($data, $this->tokenResponseKey);

        return [
            'token'      => (string) $token,
            'expires_in' => (int) ($data['expires_in'] ?? $this->fallbackTtl),
            'raw'        => $data,
        ];
    }
}
