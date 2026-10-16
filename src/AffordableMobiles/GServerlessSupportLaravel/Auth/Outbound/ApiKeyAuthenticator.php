<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Contracts\RequestAuthenticatorInterface;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\RequestInterface;

class ApiKeyAuthenticator implements RequestAuthenticatorInterface
{
    /**
     * @param string               $key          API key value
     * @param string               $name         Header name, query parameter name, or body parameter name
     * @param string               $in           Location: 'header', 'query', or 'form_params' / 'body'
     * @param null|string          $prefix       Optional prefix for header (e.g. 'Bearer', 'Token')
     * @param array<string,string> $extraHeaders Additional static headers to inject
     */
    public function __construct(
        protected readonly string $key,
        protected readonly string $name = 'X-API-Key',
        protected readonly string $in = 'header',
        protected readonly ?string $prefix = null,
        protected readonly array $extraHeaders = [],
    ) {}

    public function authenticate(RequestInterface $request, array &$options = []): RequestInterface
    {
        if ('query' === $this->in) {
            $uri      = $request->getUri();
            $query    = $uri->getQuery();
            $newQuery = '' !== $query
                ? $query.'&'.rawurlencode($this->name).'='.rawurlencode($this->key)
                : rawurlencode($this->name).'='.rawurlencode($this->key);

            $request = $request->withUri(new Uri((string) $uri->withQuery($newQuery)));
        } elseif ('form_params' === $this->in || 'body' === $this->in) {
            $body    = (string) $request->getBody();
            $pair    = rawurlencode($this->name).'='.rawurlencode($this->key);
            $newBody = '' !== $body ? $body.'&'.$pair : $pair;
            $request = $request->withBody(Utils::streamFor($newBody));

            if (!$request->hasHeader('Content-Type')) {
                $request = $request->withHeader('Content-Type', 'application/x-www-form-urlencoded');
            }
        } else {
            $headerValue = null !== $this->prefix && '' !== $this->prefix
                ? $this->prefix.' '.$this->key
                : $this->key;

            $request = $request->withHeader($this->name, $headerValue);
        }

        foreach ($this->extraHeaders as $headerKey => $headerVal) {
            $request = $request->withHeader($headerKey, $headerVal);
        }

        return $request;
    }
}
