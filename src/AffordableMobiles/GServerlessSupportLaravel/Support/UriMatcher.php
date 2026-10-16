<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Support;

use Illuminate\Support\Str;
use Psr\Http\Message\UriInterface;

class UriMatcher
{
    /**
     * Match a URI (or host and path) against a pattern mapping array.
     *
     * Patterns can be:
     * - Exact host: 'api.example.com'
     * - Wildcard host: '*.example.com'
     * - Host with path pattern: 'api.example.com/v1/*' or '*.internal.net/admin/*'
     * - Host with nested 'paths' array: 'api.example.com' => ['paths' => ['/v1/*' => ...]]
     * - Host with nested path map: 'api.example.com' => ['/v1/*' => ..., '*' => ...]
     *
     * Specificity ordering ensures the most specific pattern matches first:
     * exact host + exact path > exact host + wildcard path > exact host > wildcard host + path > wildcard host > global catch-all ('*').
     *
     * @param array<string,mixed> $map
     */
    public static function match(array $map, string|UriInterface $uri): mixed
    {
        [$host, $path] = self::parseUri($uri);

        return self::matchHostAndPath($map, $host, $path);
    }

    /**
     * Match a host and path against a pattern mapping array.
     *
     * @param array<string,mixed> $map
     */
    public static function matchHostAndPath(array $map, string $host, string $path = '/'): mixed
    {
        if (empty($map)) {
            return null;
        }

        $host = strtolower(trim($host));
        $path = '/'.ltrim(trim($path), '/');

        $candidates = [];

        foreach ($map as $key => $value) {
            $key = strtolower(trim((string) $key));

            // 1. Key contains a path component (e.g. 'host/path' or 'host/path/*')
            if (str_contains($key, '/')) {
                [$hostPattern, $pathPattern] = explode('/', $key, 2);
                $pathPattern                 = '/'.$pathPattern;

                if (Str::is($hostPattern, $host) && Str::is($pathPattern, $path)) {
                    $candidates[] = [
                        'score' => self::calculateScore($hostPattern, $pathPattern),
                        'value' => $value,
                    ];
                }

                continue;
            }

            // 2. Key is host-only (exact match, wildcard, or catch-all '*')
            if (Str::is($key, $host)) {
                // If value has a nested 'paths' configuration array
                if (\is_array($value) && isset($value['paths']) && \is_array($value['paths'])) {
                    foreach ($value['paths'] as $subPath => $subValue) {
                        $subPathPattern = '/'.ltrim((string) $subPath, '/');
                        if (Str::is($subPathPattern, $path)) {
                            $merged = \is_array($subValue) ? array_merge($value, $subValue) : $subValue;
                            if (\is_array($merged)) {
                                unset($merged['paths']);
                            }
                            $candidates[] = [
                                'score' => self::calculateScore($key, $subPathPattern),
                                'value' => $merged,
                            ];
                        }
                    }
                }

                // If value is a path-to-value map (keys are paths or '*')
                if (\is_array($value) && self::isPathMap($value)) {
                    foreach ($value as $subPath => $subValue) {
                        $subPathPattern = '/'.ltrim((string) $subPath, '/');
                        if (Str::is($subPathPattern, $path)) {
                            $candidates[] = [
                                'score' => self::calculateScore($key, $subPathPattern),
                                'value' => $subValue,
                            ];
                        }
                    }

                    continue;
                }

                // General host match (path-agnostic)
                $candidates[] = [
                    'score' => self::calculateScore($key, null),
                    'value' => $value,
                ];
            }
        }

        if (empty($candidates)) {
            return null;
        }

        // Sort candidates by score descending (highest specificity wins)
        usort($candidates, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $candidates[0]['value'];
    }

    /**
     * Parse host and path from a UriInterface or string.
     *
     * @return array{0: string, 1: string}
     */
    public static function parseUri(string|UriInterface $uri): array
    {
        if ($uri instanceof UriInterface) {
            return [
                $uri->getHost(),
                $uri->getPath() ?: '/',
            ];
        }

        $str = (string) $uri;

        if (str_contains($str, '://')) {
            $host = parse_url($str, PHP_URL_HOST) ?? '';
            $path = parse_url($str, PHP_URL_PATH) ?: '/';

            return [$host, $path];
        }

        if (str_contains($str, '/')) {
            [$h, $p] = explode('/', $str, 2);

            return [$h, '/'.$p];
        }

        return [$str, '/'];
    }

    /**
     * Check if an array represents a path-to-value map (e.g. ['/v1/*' => 'aud-1', '*' => 'aud-2']).
     */
    private static function isPathMap(array $arr): bool
    {
        if (empty($arr) || isset($arr['audience']) || isset($arr['report_5xx'])) {
            return false;
        }

        foreach (array_keys($arr) as $k) {
            if (\is_string($k) && (str_starts_with($k, '/') || '*' === $k)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate specificity score for a host and path pattern combination.
     */
    private static function calculateScore(string $hostPattern, ?string $pathPattern): int
    {
        $score = 0;

        // Host scoring
        if ('*' === $hostPattern) {
            $score += 10;
        } elseif (str_contains($hostPattern, '*')) {
            $score += 100 + \strlen($hostPattern);
        } else {
            $score += 1000 + \strlen($hostPattern);
        }

        // Path scoring
        if (null !== $pathPattern) {
            if ('/*' === $pathPattern || '*' === $pathPattern) {
                $score += 20;
            } elseif (str_contains($pathPattern, '*')) {
                $score += 200 + \strlen($pathPattern);
            } else {
                $score += 2000 + \strlen($pathPattern);
            }
        }

        return $score;
    }
}
