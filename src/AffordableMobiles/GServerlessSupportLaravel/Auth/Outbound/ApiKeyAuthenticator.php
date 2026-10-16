<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Contracts\RequestAuthenticatorInterface;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;

class ApiKeyAuthenticator implements RequestAuthenticatorInterface
{
    /**
     * @param string      $key    API key value
     * @param string      $name   Header name or query parameter name
     * @param string      $in     Location: 'header' or 'query'
     * @param null|string $prefix Optional prefix for header (e.g. 'Bearer', 'Token')
     */
    public function __construct(
        protected readonly string $key,
        protected readonly string $name = 'X-API-Key',
        protected readonly string $in = 'header',
        protected readonly ?string $prefix = null,
    ) {}

    public function authenticate(RequestInterface $request, array $options = []): RequestInterface
    {
        if ('query' === $this->in) {
            $uri      = $request->getUri();
            $query    = $uri->getQuery();
            $newQuery = '' !== $query
                ? $query.'&'.rawurlencode($this->name).'='.rawurlencode($this->key)
                : rawurlencode($this->name).'='.rawurlencode($this->key);

            return $request->withUri(new Uri((string) $uri->withQuery($newQuery)));
        }

        $headerValue = null !== $this->prefix && '' !== $this->prefix
            ? $this->prefix.' '.$this->key
            : $this->key;

        return $request->withHeader($this->name, $headerValue);
    }
}
