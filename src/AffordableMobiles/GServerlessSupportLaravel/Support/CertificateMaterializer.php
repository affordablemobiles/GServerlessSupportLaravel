<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Support;

class CertificateMaterializer
{
    /**
     * Default system cache path fallback.
     */
    public const DEFAULT_SYSTEM_CACHE_PATH = '/tmp/cache/GServerlessSupportLaravel';

    /**
     * Normalize PEM content (converting literal \n escape sequences to real newlines, trimming whitespace).
     */
    public static function normalize(string $pem): string
    {
        $pem = trim($pem);

        if (str_contains($pem, '\n')) {
            $pem = str_replace('\n', "\n", $pem);
        }

        return trim($pem);
    }

    /**
     * Check if a string is a raw PEM-encoded certificate or key.
     */
    public static function isPem(string $value): bool
    {
        $trimmed = trim($value);

        return str_starts_with($trimmed, '-----BEGIN ') || str_contains($trimmed, '-----BEGIN ');
    }

    /**
     * Bind client certificate options to Guzzle request options array.
     *
     * Preferred (Default): If CURLOPT_SSLCERT_BLOB is supported and the cert is a PEM string,
     * injects the raw PEM in-memory directly into $options['curl'][CURLOPT_SSLCERT_BLOB],
     * eliminating disk I/O and temporary files entirely.
     *
     * Fallback: If in-memory is disabled, or if an existing file path is supplied, materializes
     * to a content-addressed tmpfs file: /tmp/cache/GServerlessSupportLaravel/certs/cert_<sha256>.pem
     * and sets Guzzle's $options['cert'] and $options['ssl_key'].
     *
     * @param array<string,mixed> $options
     */
    public static function bindToRequestOptions(
        array &$options,
        array|string $cert,
        null|array|string $key = null,
        ?string $certPassword = null,
        ?string $keyPassword = null,
        bool $inMemory = true,
    ): void {
        if (\is_array($cert)) {
            $certPassword = $cert[1] ?? $certPassword;
            $cert         = (string) ($cert[0] ?? '');
        }

        if (\is_array($key)) {
            $keyPassword = $key[1] ?? $keyPassword;
            $key         = (string) ($key[0] ?? '');
        }

        $certStr = (string) $cert;
        $keyStr  = null !== $key ? (string) $key : null;

        // 1. In-Memory cURL Blob binding (Preferred)
        if ($inMemory && \defined('CURLOPT_SSLCERT_BLOB') && self::isPem($certStr)) {
            $options['curl'] ??= [];
            $options['curl'][CURLOPT_SSLCERT_BLOB] = self::normalize($certStr);

            if (null !== $certPassword && '' !== $certPassword && \defined('CURLOPT_SSLCERTPASSWD')) {
                $options['curl'][CURLOPT_SSLCERTPASSWD] = $certPassword;
            }

            if (null !== $keyStr && '' !== $keyStr && \defined('CURLOPT_SSLKEY_BLOB')) {
                $options['curl'][CURLOPT_SSLKEY_BLOB] = self::normalize($keyStr);

                $keyPass = $keyPassword ?? $certPassword;
                if (null !== $keyPass && '' !== $keyPass && \defined('CURLOPT_KEYPASSWD')) {
                    $options['curl'][CURLOPT_KEYPASSWD] = $keyPass;
                }
            }

            return;
        }

        // 2. File-based materialization (Fallback or explicit file path)
        $options['cert'] = self::materialize($certStr, $certPassword);

        if (null !== $keyStr && '' !== $keyStr) {
            $options['ssl_key'] = self::materialize($keyStr, $keyPassword);
        }
    }

    /**
     * Materialize a certificate or private key to a content-addressed file on disk.
     *
     * If the input is already an existing file path, returns the path directly.
     * If the input is a PEM string, normalizes it and writes to a content-addressed
     * file: /tmp/cache/GServerlessSupportLaravel/certs/cert_<sha256>.pem with chmod 0600.
     *
     * @param array|string $cert       PEM string, file path, or [cert, passphrase]
     * @param null|string  $passphrase Optional passphrase
     *
     * @return array|string File path string, or [filePath, passphrase]
     */
    public static function materialize(array|string $cert, ?string $passphrase = null): array|string
    {
        if (\is_array($cert)) {
            $passphrase = $cert[1] ?? $passphrase;
            $cert       = (string) ($cert[0] ?? '');
        }

        $filePath = self::materializePem((string) $cert);

        return null !== $passphrase && '' !== $passphrase ? [$filePath, $passphrase] : $filePath;
    }

    /**
     * Materialize a raw PEM string to a content-addressed file on disk, or return existing file path.
     */
    public static function materializePem(string $certOrPath): string
    {
        $certOrPath = trim($certOrPath);

        if ('' === $certOrPath) {
            return '';
        }

        // Already an existing file path on disk
        if (file_exists($certOrPath)) {
            return $certOrPath;
        }

        $normalized = self::normalize($certOrPath);
        $hash       = hash('sha256', $normalized);

        $baseDir = self::getCacheDirectory();
        $dir     = $baseDir.'/certs';

        if (!is_dir($dir)) {
            @mkdir($dir, 0o700, true);
        }

        $targetFile = $dir.'/cert_'.$hash.'.pem';

        // Content-addressed: if file already exists, reuse it without disk write
        if (!file_exists($targetFile)) {
            $tempFile = $targetFile.'.'.bin2hex(random_bytes(8)).'.tmp';
            file_put_contents($tempFile, $normalized);
            @chmod($tempFile, 0o600);
            @rename($tempFile, $targetFile);
        }

        return $targetFile;
    }

    /**
     * Get base directory for system cache.
     */
    protected static function getCacheDirectory(): string
    {
        try {
            return (string) config('gserverlesssupport.system_cache_path', self::DEFAULT_SYSTEM_CACHE_PATH);
        } catch (\Throwable) {
            return self::DEFAULT_SYSTEM_CACHE_PATH;
        }
    }
}
