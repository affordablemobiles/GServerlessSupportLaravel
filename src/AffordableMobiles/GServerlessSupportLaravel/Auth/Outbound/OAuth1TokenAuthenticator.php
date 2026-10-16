<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Contracts\RequestAuthenticatorInterface;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Exception\ConfigurationException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

class OAuth1TokenAuthenticator implements RequestAuthenticatorInterface
{
    /**
     * @param string      $consumerKey     OAuth 1.0 consumer key / client key
     * @param string      $consumerSecret  OAuth 1.0 consumer secret / client secret
     * @param string      $tokenId         Token ID / access token
     * @param string      $tokenSecret     Token secret
     * @param null|string $realm           Optional realm (e.g. NetSuite account ID)
     * @param string      $signatureMethod Signature method ('HMAC-SHA256' or 'HMAC-SHA1')
     */
    public function __construct(
        protected readonly string $consumerKey,
        protected readonly string $consumerSecret,
        protected readonly string $tokenId,
        protected readonly string $tokenSecret,
        protected readonly ?string $realm = null,
        protected readonly string $signatureMethod = 'HMAC-SHA256',
    ) {
        if (!\in_array(strtoupper($signatureMethod), ['HMAC-SHA256', 'HMAC-SHA1'], true)) {
            throw new ConfigurationException(\sprintf('Unsupported OAuth 1.0 signature method [%s]. Supported: HMAC-SHA256, HMAC-SHA1', $signatureMethod));
        }
    }

    public function authenticate(RequestInterface $request, array &$options = []): RequestInterface
    {
        $oauthParams = [
            'oauth_consumer_key'     => $this->consumerKey,
            'oauth_token'            => $this->tokenId,
            'oauth_signature_method' => strtoupper($this->signatureMethod),
            'oauth_timestamp'        => (string) time(),
            'oauth_nonce'            => bin2hex(random_bytes(16)),
            'oauth_version'          => '1.0',
        ];

        // 1. Collect all parameters (query string + oauth params)
        $allParams = $oauthParams;

        $query = $request->getUri()->getQuery();
        if ('' !== $query) {
            parse_str($query, $queryParams);
            foreach ($queryParams as $k => $v) {
                $allParams[$k] = $v;
            }
        }

        // Include form-urlencoded body params if applicable
        $contentType = $request->getHeaderLine('Content-Type');
        if (str_starts_with(strtolower($contentType), 'application/x-www-form-urlencoded')) {
            $body = (string) $request->getBody();
            if ('' !== $body) {
                parse_str($body, $bodyParams);
                foreach ($bodyParams as $k => $v) {
                    $allParams[$k] = $v;
                }
            }
        }

        // 2. Normalize and sort parameters according to RFC 5849 §3.4.1.3.2
        $normalizedParamString = $this->buildNormalizedParameterString($allParams);

        // 3. Build Signature Base String according to RFC 5849 §3.4.1.1
        $method        = strtoupper($request->getMethod());
        $normalizedUrl = $this->getNormalizedBaseUrl($request->getUri());
        $baseString    = rawurlencode($method).'&'.rawurlencode($normalizedUrl).'&'.rawurlencode($normalizedParamString);

        // 4. Calculate signature
        $key       = rawurlencode($this->consumerSecret).'&'.rawurlencode($this->tokenSecret);
        $signature = $this->calculateSignature($baseString, $key);

        $oauthParams['oauth_signature'] = $signature;

        // 5. Build Authorization: OAuth header according to RFC 5849 §3.5.1
        $headerValues = [];
        if (null !== $this->realm && '' !== $this->realm) {
            $headerValues[] = 'realm="'.rawurlencode($this->realm).'"';
        }

        foreach ($oauthParams as $k => $v) {
            $headerValues[] = rawurlencode($k).'="'.rawurlencode((string) $v).'"';
        }

        return $request->withHeader('Authorization', 'OAuth '.implode(', ', $headerValues));
    }

    /**
     * Normalize URL per RFC 5849 §3.4.1.2.
     */
    protected function getNormalizedBaseUrl(UriInterface $uri): string
    {
        $scheme = strtolower($uri->getScheme());
        $host   = strtolower($uri->getHost());
        $port   = $uri->getPort();

        $portString = '';
        if (null !== $port && !(('http' === $scheme && 80 === $port) || ('https' === $scheme && 443 === $port))) {
            $portString = ':'.$port;
        }

        $path = $uri->getPath();
        if ('' === $path) {
            $path = '/';
        }

        return \sprintf('%s://%s%s%s', $scheme, $host, $portString, $path);
    }

    /**
     * Build normalized parameter string per RFC 5849 §3.4.1.3.2.
     *
     * @param array<string,mixed> $params
     */
    protected function buildNormalizedParameterString(array $params): string
    {
        $encodedPairs = [];

        foreach ($params as $key => $value) {
            if (\is_array($value)) {
                foreach ($value as $subValue) {
                    $encodedPairs[] = [rawurlencode((string) $key), rawurlencode((string) $subValue)];
                }
            } else {
                $encodedPairs[] = [rawurlencode((string) $key), rawurlencode((string) $value)];
            }
        }

        // Sort by encoded name ascending, then by encoded value ascending
        usort($encodedPairs, static function (array $a, array $b): int {
            $cmp = strcmp($a[0], $b[0]);

            return 0 !== $cmp ? $cmp : strcmp($a[1], $b[1]);
        });

        $pairs = array_map(static fn (array $pair): string => $pair[0].'='.$pair[1], $encodedPairs);

        return implode('&', $pairs);
    }

    /**
     * Calculate HMAC signature.
     */
    protected function calculateSignature(string $baseString, string $key): string
    {
        $algo = 'HMAC-SHA1' === strtoupper($this->signatureMethod) ? 'sha1' : 'sha256';

        return base64_encode(hash_hmac($algo, $baseString, $key, true));
    }
}
