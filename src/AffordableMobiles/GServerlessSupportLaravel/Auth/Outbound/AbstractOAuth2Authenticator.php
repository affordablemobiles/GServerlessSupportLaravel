<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Contracts\RequestAuthenticatorInterface;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Exception\InvalidClientCredentialsException;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Exception\TokenExchangeException;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Token\TokenCache;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Message;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

abstract class AbstractOAuth2Authenticator implements RequestAuthenticatorInterface
{
    public const DEFAULT_FALLBACK_TTL   = 3600;
    public const DEFAULT_BUFFER_SECONDS = 120;

    /**
     * @param string               $tokenUrl             OAuth 2.0 token endpoint URL
     * @param string               $headerName           Header to inject token into (default 'Authorization')
     * @param string               $tokenType            Token prefix (default 'Bearer')
     * @param array<string,string> $extraResourceHeaders Extra headers to inject on authenticated resource requests
     * @param array<string,mixed>  $tokenClientOptions   Options for internal token endpoint Guzzle client (proxy, verify, headers)
     * @param string               $tokenResponseKey     JSON key or dot-notation path to extract token from response
     */
    public function __construct(
        protected readonly string $tokenUrl,
        protected readonly string $headerName = 'Authorization',
        protected readonly string $tokenType = 'Bearer',
        protected readonly array $extraResourceHeaders = [],
        protected readonly array $tokenClientOptions = [],
        protected readonly string $tokenResponseKey = 'access_token',
        protected readonly int $fallbackTtl = self::DEFAULT_FALLBACK_TTL,
        protected readonly int $bufferSeconds = self::DEFAULT_BUFFER_SECONDS,
    ) {}

    public function authenticate(RequestInterface $request, array &$options = []): RequestInterface
    {
        $token = TokenCache::remember(
            $this->getCacheKey(),
            fn (): array => $this->fetchToken($options),
            $this->fallbackTtl,
            $this->bufferSeconds,
        );

        $authValue = '' !== $this->tokenType ? $this->tokenType.' '.$token : $token;
        $request   = $request->withHeader($this->headerName, $authValue);

        foreach ($this->extraResourceHeaders as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    /**
     * Invalidate cached token (e.g. after target returns 401).
     */
    public function invalidateToken(): bool
    {
        return TokenCache::forget($this->getCacheKey());
    }

    /**
     * Cache key uniquely identifying this credential set.
     */
    abstract protected function getCacheKey(): string;

    /**
     * Fetch access token from remote token endpoint.
     *
     * @param array<string,mixed> $options
     *
     * @return array{token: string, expires_in: int, raw?: array<string,mixed>}
     */
    abstract protected function fetchToken(array $options = []): array;

    /**
     * Execute HTTP token request with clean Guzzle client and standard error handling.
     *
     * @param array<string,mixed> $requestOptions
     * @param array<string,mixed> $runtimeOptions
     *
     * @return array<string,mixed>
     */
    protected function executeTokenRequest(array $requestOptions, array $runtimeOptions = []): array
    {
        $clientConfig = array_merge([
            'timeout'         => 10,
            'connect_timeout' => 5,
            'http_errors'     => true,
        ], $this->tokenClientOptions);

        if (isset($runtimeOptions['proxy']) && !isset($clientConfig['proxy'])) {
            $clientConfig['proxy'] = $runtimeOptions['proxy'];
        }

        if (isset($runtimeOptions['verify']) && !isset($clientConfig['verify'])) {
            $clientConfig['verify'] = $runtimeOptions['verify'];
        }

        if (isset($runtimeOptions['cert']) && !isset($clientConfig['cert'])) {
            $clientConfig['cert'] = $runtimeOptions['cert'];
        }

        if (isset($runtimeOptions['ssl_key']) && !isset($clientConfig['ssl_key'])) {
            $clientConfig['ssl_key'] = $runtimeOptions['ssl_key'];
        }

        if (isset($runtimeOptions['curl']) && \is_array($runtimeOptions['curl'])) {
            $clientConfig['curl'] = array_replace($clientConfig['curl'] ?? [], $runtimeOptions['curl']);
        }

        $client = new GuzzleClient($clientConfig);

        try {
            $response = $client->post($this->tokenUrl, $requestOptions);
        } catch (BadResponseException $e) {
            $this->handleTokenErrorResponse($e->getResponse());
        } catch (\Throwable $e) {
            $host = parse_url($this->tokenUrl, PHP_URL_HOST) ?? 'unknown-host';

            throw new TokenExchangeException(
                message: \sprintf("[%s] POST -> Connection Error\nURL: %s\nResponse summary:\n%s", $host, $this->tokenUrl, $e->getMessage()),
                code: (int) $e->getCode(),
                previous: $e,
                tokenEndpoint: $this->tokenUrl,
            );
        }

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        $token = \is_array($data) ? data_get($data, $this->tokenResponseKey) : null;

        if (empty($token) || !\is_string($token)) {
            $host = parse_url($this->tokenUrl, PHP_URL_HOST) ?? 'unknown-host';

            throw new TokenExchangeException(
                message: \sprintf("[%s] POST -> 200 OK\nURL: %s\nResponse summary:\nMissing token response key [%s]", $host, $this->tokenUrl, $this->tokenResponseKey),
                tokenEndpoint: $this->tokenUrl,
                response: $response,
            );
        }

        return $data;
    }

    /**
     * Inspect error response from token endpoint and throw typed exception.
     */
    protected function handleTokenErrorResponse(?ResponseInterface $response): never
    {
        $host   = parse_url($this->tokenUrl, PHP_URL_HOST) ?? 'unknown-host';
        $status = $response?->getStatusCode()              ?? 0;
        $reason = $response?->getReasonPhrase()            ?? 'Error';
        $body   = $response ? (string) $response->getBody() : '';
        $data   = json_decode($body, true);

        $error       = \is_array($data) ? ($data['error'] ?? '') : '';
        $description = \is_array($data) ? ($data['error_description'] ?? '') : '';

        $firstLine = \sprintf('[%s] POST -> %d %s', $host, $status, $reason);
        $urlLine   = \sprintf('URL: %s', $this->tokenUrl);

        $summary = '' !== $description
            ? $description
            : ('' !== $error ? $error : ($response ? (Message::bodySummary($response, 250) ?? 'No response body') : 'No response body'));

        $message = \sprintf("%s\n%s\nResponse summary:\n%s", $firstLine, $urlLine, $summary);

        if (401 === $status || 'invalid_client' === $error) {
            throw new InvalidClientCredentialsException(
                message: $message,
                code: $status,
                tokenEndpoint: $this->tokenUrl,
                response: $response,
            );
        }

        throw new TokenExchangeException(
            message: $message,
            code: $status,
            tokenEndpoint: $this->tokenUrl,
            response: $response,
        );
    }
}
