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
