<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Integration\ErrorReporting\ClientSideJavaScript\Services;

/**
 * A singleton service to store assets that should be preloaded.
 * This acts as a request-scoped registry to pass links from the view
 * to the middleware.
 */
class PreloadService
{
    /** @var array<string, string> */
    protected array $links = [];

    public function add(string $url, string $as): void
    {
        // Use the URL as a key to prevent duplicates
        $this->links[$url] = "<{$url}>; rel=preload; as={$as}";
    }

    /**
     * @return array<string, string>
     */
    public function getLinks(): array
    {
        return $this->links;
    }

    public function clear(): void
    {
        $this->links = [];
    }
}
