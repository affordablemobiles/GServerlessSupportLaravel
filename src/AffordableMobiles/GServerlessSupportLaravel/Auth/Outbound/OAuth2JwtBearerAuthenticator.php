<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Exception\ConfigurationException;
use AffordableMobiles\GServerlessSupportLaravel\Auth\OutboundAuth;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Token\TokenCache;
use AffordableMobiles\GServerlessSupportLaravel\Integration\JWT\Signer\IamSigner;
use AffordableMobiles\GServerlessSupportLaravel\Integration\JWT\Signer\JwtSignerInterface;
use AffordableMobiles\GServerlessSupportLaravel\Integration\JWT\Signer\KmsSigner;
use AffordableMobiles\GServerlessSupportLaravel\Integration\JWT\Signer\OpenSslSigner;

class OAuth2JwtBearerAuthenticator extends AbstractOAuth2Authenticator
{
    public const DEFAULT_JWT_TTL = 300;

    protected readonly JwtSignerInterface $signer;

    /**
     * @param string                         $tokenUrl             OAuth token endpoint URL
     * @param array|JwtSignerInterface|mixed $signer               Signer instance or configuration array
     * @param array<string,mixed>            $claims               JWT claims (iss, sub, aud, etc.)
     * @param string                         $grantType            Grant type ('client_credentials' or 'urn:ietf:params:oauth:grant-type:jwt-bearer')
     * @param null|array|string              $basicAuth            Optional Basic Auth credentials [user, pass] or string for token endpoint
     * @param null|string                    $scope                Optional scope
     * @param bool                           $includeJti           Whether to automatically generate and include jti claim
     * @param array<string,mixed>            $extraTokenParams     Additional token request parameters
     * @param array<string,string>           $extraResourceHeaders Additional headers for resource request
     * @param array<string,mixed>            $tokenClientOptions   Custom Guzzle options for token endpoint (proxy, verify, etc.)
     * @param string                         $tokenResponseKey     JSON key to extract token from (default 'access_token')
     */
    public function __construct(
        string $tokenUrl,
        mixed $signer,
        protected readonly array $claims,
        protected readonly string $grantType = 'client_credentials',
        protected readonly null|array|string $basicAuth = null,
        protected readonly ?string $scope = null,
        protected readonly bool $includeJti = true,
        protected readonly array $extraTokenParams = [],
        array $extraResourceHeaders = [],
        array $tokenClientOptions = [],
        string $tokenResponseKey = 'access_token',
        string $headerName = 'Authorization',
        string $tokenType = 'Bearer',
        protected readonly int $jwtTtl = self::DEFAULT_JWT_TTL,
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

        $this->signer = $this->resolveSigner($signer);
    }

    protected function getCacheKey(): string
    {
        return TokenCache::key([
            'driver'    => 'jwt_bearer',
            'token_url' => $this->tokenUrl,
            'iss'       => $this->claims['iss'] ?? '',
            'sub'       => $this->claims['sub'] ?? '',
            'aud'       => $this->claims['aud'] ?? '',
            'scope'     => $this->scope         ?? '',
        ]);
    }

    protected function fetchToken(array $options = []): array
    {
        $assertion = $this->generateJwtAssertion();

        $formParams = array_merge([
            'grant_type' => $this->grantType,
        ], $this->extraTokenParams);

        if ('client_credentials' === $this->grantType) {
            $formParams['client_assertion_type'] = 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer';
            $formParams['client_assertion']      = $assertion;
        } else {
            $formParams['assertion'] = $assertion;
        }

        if (null !== $this->scope && '' !== $this->scope) {
            $formParams['scope'] = $this->scope;
        }

        $requestOptions = [
            'form_params' => $formParams,
        ];

        if (null !== $this->basicAuth) {
            if (\is_array($this->basicAuth)) {
                $requestOptions['auth'] = $this->basicAuth;
            } elseif (str_contains((string) $this->basicAuth, ':')) {
                $requestOptions['auth'] = explode(':', (string) $this->basicAuth, 2);
            } else {
                $requestOptions['headers'] ??= [];
                $requestOptions['headers']['Authorization'] = str_starts_with((string) $this->basicAuth, 'Basic ')
                    ? (string) $this->basicAuth
                    : 'Basic '.trim((string) $this->basicAuth);
            }
        }

        $data = $this->executeTokenRequest($requestOptions, $options);

        $token = data_get($data, $this->tokenResponseKey);

        return [
            'token'      => (string) $token,
            'expires_in' => (int) ($data['expires_in'] ?? $this->fallbackTtl),
            'raw'        => $data,
        ];
    }

    /**
     * Build and sign an RFC 7519 / RFC 7523 JWT assertion.
     */
    protected function generateJwtAssertion(): string
    {
        $header = [
            'alg' => $this->signer->getAlgorithm(),
            'typ' => 'JWT',
        ];

        $kidBefore = $this->signer->getKid();
        if (null !== $kidBefore) {
            $header['kid'] = $kidBefore;
        }

        $now           = time();
        $defaultClaims = [
            'iss' => $this->claims['iss'] ?? '',
            'sub' => $this->claims['sub'] ?? $this->claims['iss'] ?? '',
            'aud' => $this->claims['aud'] ?? $this->tokenUrl,
            'iat' => $now,
            'exp' => $now + $this->jwtTtl,
        ];

        if ($this->includeJti && !isset($this->claims['jti'])) {
            $defaultClaims['jti'] = bin2hex(random_bytes(16));
        }

        $payload = array_merge($defaultClaims, $this->claims);

        $signingInput = self::base64UrlEncode((string) json_encode($header, JSON_UNESCAPED_SLASHES))
            .'.'
            .self::base64UrlEncode((string) json_encode($payload, JSON_UNESCAPED_SLASHES));

        $signature = $this->signer->sign($signingInput);

        // If the signer auto-discovered or rotated its keyId during sign() (e.g. IamSigner on cold start or key rotation)
        if ($this->signer->getKid() !== $kidBefore) {
            $header['kid'] = $this->signer->getKid();
            $signingInput  = self::base64UrlEncode((string) json_encode($header, JSON_UNESCAPED_SLASHES))
                .'.'
                .self::base64UrlEncode((string) json_encode($payload, JSON_UNESCAPED_SLASHES));
            $signature     = $this->signer->sign($signingInput);
        }

        return $signingInput.'.'.self::base64UrlEncode($signature);
    }

    protected static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    protected function resolveSigner(mixed $signer): JwtSignerInterface
    {
        if ($signer instanceof JwtSignerInterface) {
            return $signer;
        }

        if (\is_array($signer)) {
            $type      = (string) ($signer['driver'] ?? $signer['type'] ?? 'openssl');
            $typeLower = strtolower($type);

            $customSigners = OutboundAuth::getCustomSigners();
            if (isset($customSigners[$typeLower])) {
                return ($customSigners[$typeLower])($signer);
            }

            if (class_exists($type) && is_subclass_of($type, JwtSignerInterface::class)) {
                return method_exists($type, 'fromConfig')
                    ? $type::fromConfig($signer)
                    : app()->make($type, ['config' => $signer]);
            }

            return match ($typeLower) {
                'iam', 'gcp_iam' => new IamSigner(
                    serviceAccountEmail: (string) ($signer['service_account'] ?? $signer['email'] ?? ''),
                    kid: isset($signer['kid']) ? (string) $signer['kid'] : null,
                ),
                'kms', 'gcp_kms' => new KmsSigner(
                    keyVersionName: (string) ($signer['key_version'] ?? $signer['key'] ?? ''),
                    kid: isset($signer['kid']) ? (string) $signer['kid'] : null,
                    algorithm: (string) ($signer['algorithm'] ?? 'RS256'),
                ),
                'openssl', 'pem' => new OpenSslSigner(
                    privateKey: (string) ($signer['private_key'] ?? $signer['key'] ?? ''),
                    passphrase: isset($signer['passphrase']) ? (string) $signer['passphrase'] : null,
                    kid: isset($signer['kid']) ? (string) $signer['kid'] : null,
                    algorithm: (string) ($signer['algorithm'] ?? 'RS256'),
                ),
                default => throw new ConfigurationException(\sprintf('Unknown JWT signer type [%s]', $type)),
            };
        }

        throw new ConfigurationException('Invalid JWT signer configuration');
    }
}
