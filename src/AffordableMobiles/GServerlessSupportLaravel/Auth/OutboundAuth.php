<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Contracts\RequestAuthenticatorInterface;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Exception\ConfigurationException;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound\ApiKeyAuthenticator;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound\GoogleOAuth2Authenticator;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound\GoogleOidcAuthenticator;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound\OAuth1TokenAuthenticator;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound\OAuth2ClientCredentialsAuthenticator;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound\OAuth2JwtBearerAuthenticator;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound\OAuth2PasswordAuthenticator;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound\OAuth2RefreshTokenAuthenticator;

class OutboundAuth
{
    /**
     * Request-scoped runtime authentication map for dynamic connector / route-group registration.
     *
     * @var array<string,mixed>
     */
    protected static array $requestMap = [];

    /**
     * Register an outbound authentication configuration scoped to the current request lifecycle.
     *
     * Useful in Route Middleware or Base Controllers (e.g. ConfigController) when credentials
     * depend dynamically on inbound request parameters (themeCode, brand, test/live mode).
     *
     * @param string $pattern URI pattern (e.g. 'api.example.com/*' or 'api.example.com/v1/*')
     */
    public static function registerForRequest(string $pattern, mixed $config): void
    {
        self::$requestMap[$pattern] = $config;
    }

    /**
     * Retrieve the active request-scoped authentication map.
     *
     * @return array<string,mixed>
     */
    public static function getRequestMap(): array
    {
        return self::$requestMap;
    }

    /**
     * Clear all request-scoped authentication mappings (called at end of request lifecycle).
     */
    public static function clearRequestMap(): void
    {
        self::$requestMap = [];
    }

    public static function oidc(mixed $audience): GoogleOidcAuthenticator
    {
        return new GoogleOidcAuthenticator(self::resolveCallableOrValue($audience));
    }

    public static function googleOAuth2(): GoogleOAuth2Authenticator
    {
        return new GoogleOAuth2Authenticator();
    }

    public static function clientCredentials(
        string $tokenUrl,
        string $clientId,
        string $clientSecret,
        ?string $scope = null,
        ?string $audience = null,
        string $authMethod = 'client_secret_basic',
        array $extraTokenParams = [],
        array $extraResourceHeaders = [],
        array $tokenClientOptions = [],
        string $tokenResponseKey = 'access_token',
        string $headerName = 'Authorization',
        string $tokenType = 'Bearer',
        int $fallbackTtl = 3600,
        int $bufferSeconds = 120,
    ): OAuth2ClientCredentialsAuthenticator {
        return new OAuth2ClientCredentialsAuthenticator(
            tokenUrl: $tokenUrl,
            clientId: $clientId,
            clientSecret: $clientSecret,
            scope: $scope,
            audience: $audience,
            authMethod: $authMethod,
            extraTokenParams: $extraTokenParams,
            extraResourceHeaders: $extraResourceHeaders,
            tokenClientOptions: $tokenClientOptions,
            tokenResponseKey: $tokenResponseKey,
            headerName: $headerName,
            tokenType: $tokenType,
            fallbackTtl: $fallbackTtl,
            bufferSeconds: $bufferSeconds,
        );
    }

    public static function password(
        string $tokenUrl,
        string $clientId,
        string $clientSecret,
        string $username,
        string $password,
        ?string $scope = null,
        string $authMethod = 'client_secret_basic',
        array $extraTokenParams = [],
        array $extraResourceHeaders = [],
        array $tokenClientOptions = [],
        string $tokenResponseKey = 'access_token',
        string $headerName = 'Authorization',
        string $tokenType = 'Bearer',
        int $fallbackTtl = 3600,
        int $bufferSeconds = 120,
    ): OAuth2PasswordAuthenticator {
        return new OAuth2PasswordAuthenticator(
            tokenUrl: $tokenUrl,
            clientId: $clientId,
            clientSecret: $clientSecret,
            username: $username,
            password: $password,
            scope: $scope,
            authMethod: $authMethod,
            extraTokenParams: $extraTokenParams,
            extraResourceHeaders: $extraResourceHeaders,
            tokenClientOptions: $tokenClientOptions,
            tokenResponseKey: $tokenResponseKey,
            headerName: $headerName,
            tokenType: $tokenType,
            fallbackTtl: $fallbackTtl,
            bufferSeconds: $bufferSeconds,
        );
    }

    public static function refreshToken(
        string $tokenUrl,
        string $clientId,
        string $clientSecret,
        mixed $refreshToken,
        ?string $scope = null,
        string $authMethod = 'client_secret_basic',
        mixed $onTokenRefreshed = null,
        array $extraTokenParams = [],
        array $extraResourceHeaders = [],
        array $tokenClientOptions = [],
        string $tokenResponseKey = 'access_token',
        string $headerName = 'Authorization',
        string $tokenType = 'Bearer',
        int $fallbackTtl = 3600,
        int $bufferSeconds = 120,
    ): OAuth2RefreshTokenAuthenticator {
        return new OAuth2RefreshTokenAuthenticator(
            tokenUrl: $tokenUrl,
            clientId: $clientId,
            clientSecret: $clientSecret,
            refreshToken: $refreshToken,
            scope: $scope,
            authMethod: $authMethod,
            onTokenRefreshed: $onTokenRefreshed,
            extraTokenParams: $extraTokenParams,
            extraResourceHeaders: $extraResourceHeaders,
            tokenClientOptions: $tokenClientOptions,
            tokenResponseKey: $tokenResponseKey,
            headerName: $headerName,
            tokenType: $tokenType,
            fallbackTtl: $fallbackTtl,
            bufferSeconds: $bufferSeconds,
        );
    }

    public static function jwtBearer(
        string $tokenUrl,
        mixed $signer,
        array $claims,
        string $grantType = 'client_credentials',
        null|array|string $basicAuth = null,
        ?string $scope = null,
        bool $includeJti = true,
        array $extraTokenParams = [],
        array $extraResourceHeaders = [],
        array $tokenClientOptions = [],
        string $tokenResponseKey = 'access_token',
        string $headerName = 'Authorization',
        string $tokenType = 'Bearer',
        int $jwtTtl = 300,
        int $fallbackTtl = 3600,
        int $bufferSeconds = 120,
    ): OAuth2JwtBearerAuthenticator {
        return new OAuth2JwtBearerAuthenticator(
            tokenUrl: $tokenUrl,
            signer: $signer,
            claims: $claims,
            grantType: $grantType,
            basicAuth: $basicAuth,
            scope: $scope,
            includeJti: $includeJti,
            extraTokenParams: $extraTokenParams,
            extraResourceHeaders: $extraResourceHeaders,
            tokenClientOptions: $tokenClientOptions,
            tokenResponseKey: $tokenResponseKey,
            headerName: $headerName,
            tokenType: $tokenType,
            jwtTtl: $jwtTtl,
            fallbackTtl: $fallbackTtl,
            bufferSeconds: $bufferSeconds,
        );
    }

    public static function oauth1Tba(
        string $consumerKey,
        string $consumerSecret,
        string $tokenId,
        string $tokenSecret,
        ?string $realm = null,
        string $signatureMethod = 'HMAC-SHA256',
    ): OAuth1TokenAuthenticator {
        return new OAuth1TokenAuthenticator(
            consumerKey: $consumerKey,
            consumerSecret: $consumerSecret,
            tokenId: $tokenId,
            tokenSecret: $tokenSecret,
            realm: $realm,
            signatureMethod: $signatureMethod,
        );
    }

    public static function apiKey(
        string $key,
        string $name = 'X-API-Key',
        string $in = 'header',
        ?string $prefix = null,
    ): ApiKeyAuthenticator {
        return new ApiKeyAuthenticator(
            key: $key,
            name: $name,
            in: $in,
            prefix: $prefix,
        );
    }

    public static function basic(string $username, string $password): ApiKeyAuthenticator
    {
        return new ApiKeyAuthenticator(
            key: base64_encode($username.':'.$password),
            name: 'Authorization',
            in: 'header',
            prefix: 'Basic',
        );
    }

    /**
     * Resolve authenticator from configuration array or string.
     *
     * @param array<string,mixed>|string $config
     */
    public static function fromConfig(mixed $config): ?RequestAuthenticatorInterface
    {
        if ($config instanceof RequestAuthenticatorInterface) {
            return $config;
        }

        if (\is_string($config)) {
            if ('oauth2' === $config || 'google_oauth2' === $config) {
                return self::googleOAuth2();
            }

            if ('' !== $config) {
                return self::oidc($config);
            }

            return null;
        }

        if (!\is_array($config)) {
            return null;
        }

        $driver = $config['driver'] ?? $config['type'] ?? null;

        return match ($driver) {
            'google_oidc', 'oidc' => self::oidc(self::resolveCallableOrValue($config['audience'] ?? $config['target_audience'] ?? '')),
            'google_oauth2', 'oauth2' => self::googleOAuth2(),
            'oauth2_client_credentials', 'client_credentials' => self::clientCredentials(
                tokenUrl: (string) ($config['token_url'] ?? $config['url'] ?? ''),
                clientId: (string) ($config['client_id'] ?? ''),
                clientSecret: (string) ($config['client_secret'] ?? ''),
                scope: isset($config['scope']) ? (string) $config['scope'] : null,
                audience: isset($config['audience']) ? (string) $config['audience'] : null,
                authMethod: (string) ($config['auth_method'] ?? 'client_secret_basic'),
                extraTokenParams: (array) ($config['extra_token_params'] ?? $config['token_params'] ?? []),
                extraResourceHeaders: (array) ($config['extra_resource_headers'] ?? $config['request_headers'] ?? []),
                tokenClientOptions: (array) ($config['token_client_options'] ?? []),
                tokenResponseKey: (string) ($config['token_response_key'] ?? 'access_token'),
                headerName: (string) ($config['header_name'] ?? 'Authorization'),
                tokenType: (string) ($config['token_type'] ?? 'Bearer'),
            ),
            'oauth2_password', 'password' => self::password(
                tokenUrl: (string) ($config['token_url'] ?? $config['url'] ?? ''),
                clientId: (string) ($config['client_id'] ?? ''),
                clientSecret: (string) ($config['client_secret'] ?? ''),
                username: (string) ($config['username'] ?? ''),
                password: (string) ($config['password'] ?? ''),
                scope: isset($config['scope']) ? (string) $config['scope'] : null,
                authMethod: (string) ($config['auth_method'] ?? 'client_secret_basic'),
                extraTokenParams: (array) ($config['extra_token_params'] ?? []),
                extraResourceHeaders: (array) ($config['extra_resource_headers'] ?? $config['request_headers'] ?? []),
                tokenClientOptions: (array) ($config['token_client_options'] ?? []),
                tokenResponseKey: (string) ($config['token_response_key'] ?? 'access_token'),
                headerName: (string) ($config['header_name'] ?? 'Authorization'),
                tokenType: (string) ($config['token_type'] ?? 'Bearer'),
            ),
            'oauth2_refresh_token', 'refresh_token' => self::refreshToken(
                tokenUrl: (string) ($config['token_url'] ?? $config['url'] ?? ''),
                clientId: (string) ($config['client_id'] ?? ''),
                clientSecret: (string) ($config['client_secret'] ?? ''),
                refreshToken: $config['refresh_token'] ?? '',
                scope: isset($config['scope']) ? (string) $config['scope'] : null,
                authMethod: (string) ($config['auth_method'] ?? 'client_secret_basic'),
                onTokenRefreshed: $config['on_token_refreshed'] ?? null,
                extraTokenParams: (array) ($config['extra_token_params'] ?? []),
                extraResourceHeaders: (array) ($config['extra_resource_headers'] ?? []),
                tokenClientOptions: (array) ($config['token_client_options'] ?? []),
                tokenResponseKey: (string) ($config['token_response_key'] ?? 'access_token'),
                headerName: (string) ($config['header_name'] ?? 'Authorization'),
                tokenType: (string) ($config['token_type'] ?? 'Bearer'),
            ),
            'oauth2_jwt_bearer', 'jwt_bearer' => self::jwtBearer(
                tokenUrl: (string) ($config['token_url'] ?? $config['url'] ?? ''),
                signer: $config['signer'] ?? [],
                claims: (array) ($config['claims'] ?? []),
                grantType: (string) ($config['grant_type'] ?? 'client_credentials'),
                basicAuth: $config['basic_auth'] ?? null,
                scope: isset($config['scope']) ? (string) $config['scope'] : null,
                includeJti: (bool) ($config['include_jti'] ?? true),
                extraTokenParams: (array) ($config['extra_token_params'] ?? []),
                extraResourceHeaders: (array) ($config['extra_resource_headers'] ?? $config['request_headers'] ?? []),
                tokenClientOptions: (array) ($config['token_client_options'] ?? []),
                tokenResponseKey: (string) ($config['token_response_key'] ?? 'access_token'),
                headerName: (string) ($config['header_name'] ?? 'Authorization'),
                tokenType: (string) ($config['token_type'] ?? 'Bearer'),
                jwtTtl: (int) ($config['jwt_ttl'] ?? 300),
            ),
            'oauth1_tba', 'oauth1', 'tba' => self::oauth1Tba(
                consumerKey: (string) ($config['consumer_key'] ?? ''),
                consumerSecret: (string) ($config['consumer_secret'] ?? ''),
                tokenId: (string) ($config['token_id'] ?? $config['token'] ?? ''),
                tokenSecret: (string) ($config['token_secret'] ?? ''),
                realm: isset($config['realm']) ? (string) $config['realm'] : (isset($config['account_id']) ? (string) $config['account_id'] : null),
                signatureMethod: (string) ($config['signature_method'] ?? 'HMAC-SHA256'),
            ),
            'api_key', 'key' => self::apiKey(
                key: (string) ($config['key'] ?? $config['token'] ?? ''),
                name: (string) ($config['name'] ?? $config['header'] ?? 'X-API-Key'),
                in: (string) ($config['in'] ?? 'header'),
                prefix: isset($config['prefix']) ? (string) $config['prefix'] : null,
            ),
            'basic', 'http_basic' => self::basic(
                username: (string) ($config['username'] ?? $config['user'] ?? ''),
                password: (string) ($config['password'] ?? $config['pass'] ?? ''),
            ),
            default => throw new ConfigurationException(\sprintf('Unknown outbound auth driver [%s]', (string) $driver)),
        };
    }

    /**
     * Resolve a callable, invokable class string, or raw value.
     */
    protected static function resolveCallableOrValue(mixed $value): mixed
    {
        if (\is_string($value) && class_exists($value)) {
            try {
                $instance = app($value);
                if (\is_callable($instance)) {
                    return $instance;
                }
            } catch (\Throwable) {
                return $value;
            }
        }

        return $value;
    }
}
