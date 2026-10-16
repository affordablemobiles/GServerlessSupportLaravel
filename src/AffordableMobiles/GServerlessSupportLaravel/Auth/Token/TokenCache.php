<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Token;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Psr\SimpleCache\CacheInterface;

class TokenCache
{
    public const DEFAULT_BUFFER_SECONDS = 120;
    public const DEFAULT_FALLBACK_TTL   = 3600;

    /**
     * Generate a collision-free SHA-256 cache key based on identity parameters.
     *
     * @param array<string,mixed> $params
     */
    public static function key(array $params): string
    {
        ksort($params);

        return 'outbound_token_'.hash('sha256', serialize($params));
    }

    /**
     * Retrieve an item from the instance-scoped cache, or execute the given Closure and store the result.
     */
    public static function remember(
        string $key,
        callable $callback,
        int $fallbackTtl = self::DEFAULT_FALLBACK_TTL,
        int $bufferSeconds = self::DEFAULT_BUFFER_SECONDS,
    ): string {
        $cache = self::store();

        $cached = $cache->get($key);
        if (null !== $cached && '' !== $cached) {
            return (string) $cached;
        }

        $result = $callback();

        // Callback can return either ['token' => ..., 'expires_in' => ...] or just string token
        if (\is_array($result)) {
            $token     = (string) ($result['token'] ?? $result['access_token'] ?? '');
            $expiresIn = (int) ($result['expires_in'] ?? $fallbackTtl);
        } else {
            $token     = (string) $result;
            $expiresIn = $fallbackTtl;
        }

        if ('' !== $token) {
            $ttl = max(30, $expiresIn - $bufferSeconds);
            $cache->put($key, $token, $ttl);
        }

        return $token;
    }

    /**
     * Invalidate a cached token (e.g. after a 401 response).
     */
    public static function forget(string $key): bool
    {
        return self::store()->forget($key);
    }

    /**
     * Access the instance-scoped cache repository.
     */
    protected static function store(): CacheInterface|Repository
    {
        try {
            return Cache::store('instance-scoped');
        } catch (\Throwable) {
            return Cache::store();
        }
    }
}
