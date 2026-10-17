<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Contracts\RequestAuthenticatorInterface;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Exception\ConfigurationException;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound\ApiKeyAuthenticator;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound\BasicAuthenticator;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound\ChainAuthenticator;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound\GoogleOAuth2Authenticator;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound\GoogleOidcAuthenticator;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound\MtlsAuthenticator;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound\OAuth1TokenAuthenticator;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound\OAuth2ClientCredentialsAuthenticator;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound\OAuth2JwtBearerAuthenticator;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound\OAuth2PasswordAuthenticator;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound\OAuth2RefreshTokenAuthenticator;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound\PayloadSignatureAuthenticator;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound\WsseAuthenticator;

class OutboundAuth
{
    /**
     * Request-scoped runtime authentication map for dynamic connector / route-group registration.
     *
     * @var array<string,mixed>
     */
    protected static array $requestMap = [];

    /**
     * Registry of custom authenticator driver creators.
     *
     * @var array<string,callable>
     */
    protected static array $customCreators = [];

    /**
     * Registry of custom JWT signer creators.
     *
     * @var array<string,callable>
     */
    protected static array $customSigners = [];

    /**
     * Register a custom outbound authenticator driver creator.
     */
    public static function extend(string $driver, callable $creator): void
    {
        self::$customCreators[strtolower($driver)] = $creator;
    }

    /**
     * Register a custom JWT signer creator.
     */
    public static function extendSigner(string $type, callable $creator): void
    {
        self::$customSigners[strtolower($type)] = $creator;
    }

    /**
     * Get all registered custom driver creators.
     *
     * @return array<string,callable>
     */
    public static function getCustomCreators(): array
    {
        return self::$customCreators;
    }

    /**
     * Get all registered custom signer creators.
     *
     * @return array<string,callable>
     */
    public static function getCustomSigners(): array
    {
        return self::$customSigners;
    }

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
        array $extraHeaders = [],
    ): ApiKeyAuthenticator {
        return new ApiKeyAuthenticator(
            key: $key,
            name: $name,
            in: $in,
            prefix: $prefix,
            extraHeaders: $extraHeaders,
        );
    }

    public static function basic(string $username, ?string $password = null, string $headerName = 'Authorization'): BasicAuthenticator
    {
        return new BasicAuthenticator($username, $password, $headerName);
    }

    public static function wsse(
        string $username,
        ?string $secret = null,
        string $headerName = 'X-WSSE',
        bool $binaryDigest = false,
        int $nonceLength = 32,
        ?string $password = null,
    ): WsseAuthenticator {
        $secretValue = $secret ?? $password ?? '';

        return new WsseAuthenticator(
            username: $username,
            secret: $secretValue,
            headerName: $headerName,
            binaryDigest: $binaryDigest,
            nonceLength: $nonceLength,
        );
    }

    public static function payloadSignature(
        mixed $key,
        string $headerName = 'X-Signature',
        ?string $timestampHeader = null,
        string $bodySeparator = '',
        array $extraHeaders = [],
        string $algorithm = 'sha256',
        string $signatureFormat = 'base64',
        string $signerType = 'auto',
    ): PayloadSignatureAuthenticator {
        return new PayloadSignatureAuthenticator(
            key: $key,
            headerName: $headerName,
            timestampHeader: $timestampHeader,
            bodySeparator: $bodySeparator,
            extraHeaders: $extraHeaders,
            algorithm: $algorithm,
            signatureFormat: $signatureFormat,
            signerType: $signerType,
        );
    }

    public static function mtls(
        array|string $cert,
        null|array|string $key = null,
        ?string $certPassword = null,
        ?string $keyPassword = null,
        bool $inMemory = true,
    ): MtlsAuthenticator {
        return new MtlsAuthenticator(
            cert: $cert,
            key: $key,
            certPassword: $certPassword,
            keyPassword: $keyPassword,
            inMemory: $inMemory,
        );
    }

    /**
     * @param array<RequestAuthenticatorInterface> $authenticators
     */
    public static function chain(array $authenticators): ChainAuthenticator
    {
        return new ChainAuthenticator($authenticators);
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
            if (str_starts_with($config, 'config:')) {
                return self::fromConfig(config(substr($config, 7)));
            }

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

        // Auto-detect OAuth 1.0a TBA configuration arrays without explicit driver
        if (null === $driver && isset($config['consumer_key'], $config['token_id'])) {
            $driver = 'oauth1_tba';
        }

        return match ($driver) {
            'google_oidc', 'oidc' => self::oidc(self::resolveCallableOrValue($config['audience'] ?? $config['target_audience'] ?? '')),
            'google_oauth2', 'oauth2' => self::googleOAuth2(),
            'oauth2_client_credentials', 'client_credentials' => self::clientCredentials(
                ...self::commonOAuth2Args($config),
                tokenUrl: (string) ($config['token_url'] ?? $config['url'] ?? ''),
                clientId: (string) ($config['client_id'] ?? ''),
                clientSecret: (string) ($config['client_secret'] ?? ''),
                scope: isset($config['scope']) ? (string) $config['scope'] : null,
                audience: isset($config['audience']) ? (string) $config['audience'] : null,
                authMethod: (string) ($config['auth_method'] ?? 'client_secret_basic'),
            ),
            'oauth2_password', 'password' => self::password(
                ...self::commonOAuth2Args($config),
                tokenUrl: (string) ($config['token_url'] ?? $config['url'] ?? ''),
                clientId: (string) ($config['client_id'] ?? ''),
                clientSecret: (string) ($config['client_secret'] ?? ''),
                username: (string) ($config['username'] ?? ''),
                password: (string) ($config['password'] ?? ''),
                scope: isset($config['scope']) ? (string) $config['scope'] : null,
                authMethod: (string) ($config['auth_method'] ?? 'client_secret_basic'),
            ),
            'oauth2_refresh_token', 'refresh_token' => self::refreshToken(
                ...self::commonOAuth2Args($config),
                tokenUrl: (string) ($config['token_url'] ?? $config['url'] ?? ''),
                clientId: (string) ($config['client_id'] ?? ''),
                clientSecret: (string) ($config['client_secret'] ?? ''),
                refreshToken: $config['refresh_token'] ?? '',
                scope: isset($config['scope']) ? (string) $config['scope'] : null,
                authMethod: (string) ($config['auth_method'] ?? 'client_secret_basic'),
                onTokenRefreshed: $config['on_token_refreshed'] ?? null,
            ),
            'oauth2_jwt_bearer', 'jwt_bearer' => self::jwtBearer(
                ...self::commonOAuth2Args($config),
                tokenUrl: (string) ($config['token_url'] ?? $config['url'] ?? ''),
                signer: $config['signer'] ?? [],
                claims: (array) ($config['claims'] ?? []),
                grantType: (string) ($config['grant_type'] ?? 'client_credentials'),
                basicAuth: $config['basic_auth'] ?? null,
                scope: isset($config['scope']) ? (string) $config['scope'] : null,
                includeJti: (bool) ($config['include_jti'] ?? true),
                jwtTtl: (int) ($config['jwt_ttl'] ?? 300),
            ),
            'oauth1_tba', 'oauth1', 'tba' => self::oauth1Tba(
                consumerKey: (string) ($config['consumer_key'] ?? ''),
                consumerSecret: (string) ($config['consumer_secret'] ?? ''),
                tokenId: (string) ($config['token_id'] ?? $config['token'] ?? ''),
                tokenSecret: (string) ($config['token_secret'] ?? ''),
                realm: isset($config['realm']) ? (string) $config['realm'] : (isset($config['account_id']) ? (string) $config['account_id'] : null),
                signatureMethod: isset($config['hmac']) ? 'HMAC-'.strtoupper((string) $config['hmac']) : (string) ($config['signature_method'] ?? 'HMAC-SHA256'),
            ),
            'api_key', 'key' => self::apiKey(
                key: (string) ($config['key'] ?? $config['token'] ?? ''),
                name: (string) ($config['name'] ?? $config['header'] ?? 'X-API-Key'),
                in: (string) ($config['in'] ?? 'header'),
                prefix: isset($config['prefix']) ? (string) $config['prefix'] : null,
                extraHeaders: (array) ($config['extra_headers'] ?? $config['headers'] ?? []),
            ),
            'basic', 'http_basic' => self::basic(
                username: (string) ($config['username'] ?? $config['user'] ?? $config['key'] ?? ''),
                password: isset($config['password']) ? (string) $config['password'] : (isset($config['pass']) ? (string) $config['pass'] : null),
                headerName: (string) ($config['header_name'] ?? $config['header'] ?? 'Authorization'),
            ),
            'wsse', 'x_wsse' => self::wsse(
                username: (string) ($config['username'] ?? $config['user_name'] ?? $config['user'] ?? ''),
                secret: (string) ($config['secret'] ?? $config['password'] ?? ''),
                headerName: (string) ($config['header_name'] ?? 'X-WSSE'),
                binaryDigest: (bool) ($config['binary_digest'] ?? false),
                nonceLength: (int) ($config['nonce_length'] ?? 32),
            ),
            'payload_signature', 'signature', 'hmac_signature' => self::payloadSignature(
                key: $config['key'] ?? $config['secret'] ?? '',
                headerName: (string) ($config['header_name'] ?? $config['header'] ?? 'X-Signature'),
                timestampHeader: isset($config['timestamp_header']) ? (string) $config['timestamp_header'] : null,
                bodySeparator: (string) ($config['body_separator'] ?? ''),
                extraHeaders: (array) ($config['extra_headers'] ?? $config['headers'] ?? []),
                algorithm: (string) ($config['algorithm'] ?? 'sha256'),
                signatureFormat: (string) ($config['signature_format'] ?? 'base64'),
                signerType: (string) ($config['signer_type'] ?? 'auto'),
            ),
            'mtls', 'client_cert' => self::mtls(
                cert: $config['cert']                  ?? $config['certificate'] ?? '',
                key: $config['key']                    ?? $config['ssl_key'] ?? $config['private_key'] ?? null,
                certPassword: $config['cert_password'] ?? $config['passphrase'] ?? null,
                keyPassword: $config['key_password']   ?? null,
                inMemory: (bool) ($config['in_memory'] ?? true),
            ),
            'chain' => self::chain(
                array_filter(array_map(self::fromConfig(...), (array) ($config['authenticators'] ?? $config['drivers'] ?? [])))
            ),
            default => self::resolveCustomOrClassDriver((string) $driver, $config),
        };
    }

    /**
     * Resolve a custom driver registered via extend() or an FQCN class string implementing RequestAuthenticatorInterface.
     *
     * @param array<string,mixed> $config
     */
    protected static function resolveCustomOrClassDriver(string $driver, array $config): RequestAuthenticatorInterface
    {
        $driverLower = strtolower($driver);
        if (isset(self::$customCreators[$driverLower])) {
            return (self::$customCreators[$driverLower])($config);
        }

        if (class_exists($driver) && is_subclass_of($driver, RequestAuthenticatorInterface::class)) {
            return method_exists($driver, 'fromConfig')
                ? $driver::fromConfig($config)
                : app()->make($driver, ['config' => $config]);
        }

        throw new ConfigurationException(\sprintf('Unknown outbound auth driver [%s]', $driver));
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

    /**
     * Extract shared OAuth2 options from configuration array.
     *
     * @param array<string,mixed> $config
     *
     * @return array<string,mixed>
     */
    private static function commonOAuth2Args(array $config): array
    {
        return [
            'extraTokenParams'     => (array) ($config['extra_token_params'] ?? $config['token_params'] ?? []),
            'extraResourceHeaders' => (array) ($config['extra_resource_headers'] ?? $config['request_headers'] ?? []),
            'tokenClientOptions'   => (array) ($config['token_client_options'] ?? []),
            'tokenResponseKey'     => (string) ($config['token_response_key'] ?? 'access_token'),
            'headerName'           => (string) ($config['header_name'] ?? 'Authorization'),
            'tokenType'            => (string) ($config['token_type'] ?? 'Bearer'),
        ];
    }
}
