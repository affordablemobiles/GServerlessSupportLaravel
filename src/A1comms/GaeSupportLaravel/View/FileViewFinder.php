<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\View;

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\FileViewFinder as LaravelFileViewFinder;

/**
 * Search for views in a static manifest instead of on disk,
 * hopefully resulting in less costly disk I/O.
 */
class FileViewFinder extends LaravelFileViewFinder
{
    private $manifest = [];

    private $pathCache = [];

    public function __construct(Filesystem $files, array $paths, array $extensions = null, string $cachePath = null)
    {
        $this->files = $files;
        $this->paths = $paths;

        if (isset($extensions)) {
            $this->extensions = $extensions;
        }

        if (!empty($cachePath)) {
            $manifestPath = $cachePath.'/manifest.php';
            if (is_file($manifestPath)) {
                $this->manifest = require $manifestPath;
            }
        }
    }

    public static function getRelativePath($startPath, $endPath, $dot = true)
    {
        // Normalize separators on Windows
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $endPath   = str_replace('\\', '/', $endPath);
            $startPath = str_replace('\\', '/', $startPath);
        }
        $stripDriveLetter = function ($path) {
            if (\strlen($path) > 2 && ':' === $path[1] && '/' === $path[2] && ctype_alpha($path[0])) {
                return substr($path, 2);
            }

            return $path;
        };
        $endPath   = $stripDriveLetter($endPath);
        $startPath = $stripDriveLetter($startPath);
        // Split the paths into arrays
        $startPathArr       = explode('/', trim($startPath, '/'));
        $endPathArr         = explode('/', trim($endPath, '/'));
        $normalizePathArray = function ($pathSegments, $absolute) {
            $result = [];
            foreach ($pathSegments as $segment) {
                if ('..' === $segment && ($absolute || \count($result))) {
                    array_pop($result);
                } elseif ('.' !== $segment) {
                    $result[] = $segment;
                }
            }

            return $result;
        };
        $startPathArr = $normalizePathArray($startPathArr, self::isAbsolutePath($startPath));
        $endPathArr   = $normalizePathArray($endPathArr, self::isAbsolutePath($endPath));
        // Find for which directory the common path stops
        $index = 0;
        while (isset($startPathArr[$index], $endPathArr[$index]) && $startPathArr[$index] === $endPathArr[$index]) {
            ++$index;
        }
        // Determine how deep the start path is relative to the common path (ie, "web/bundles" = 2 levels)
        if (1 === \count($startPathArr) && '' === $startPathArr[0]) {
            $depth = 0;
        } else {
            $depth = \count($startPathArr) - $index;
        }
        // Repeated "../" for each level need to reach the common path
        $traverser        = str_repeat('../', $depth);
        $endPathRemainder = implode('/', \array_slice($endPathArr, $index));
        // Construct $endPath from traversing to the common path, then to the remaining $endPath
        $relativePath = $traverser.('' !== $endPathRemainder ? $endPathRemainder/* .'/' */ : '');

        return '' === $relativePath ? './' : $relativePath;
    }

    public static function isAbsolutePath($file)
    {
        return strspn($file, '/\\', 0, 1)
            || (
                \strlen($file) > 3 && ctype_alpha($file[0])
                                   && ':' === $file[1]
                                   && strspn($file, '/\\', 2, 1)
            )
            || null !== parse_url($file, PHP_URL_SCHEME)
        ;
    }

    /**
     * Find the given view in the list of paths.
     *
     * @param string $name
     * @param array  $paths
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    protected function findInPaths($name, $paths)
    {
        foreach ((array) $paths as $path) {
            foreach ($this->getPossibleViewFiles($name) as $file) {
                /**
                 * Use relative path translation here,
                 * as our path on production will probably
                 * be different to the path in build where
                 * the templates and manifest were compiled.
                 */
                $viewPath = $this->transformViewPath($path).'/'.$file;
                if (!empty($this->manifest[$viewPath])) {
                    return $viewPath;
                }
            }
        }

        throw new \InvalidArgumentException("View [{$name}] not found.");
    }

    protected function transformViewPath($path)
    {
        if (!empty($this->pathCache[$path])) {
            return $this->pathCache[$path];
        }

        $viewPath = self::getRelativePath(base_path(), rtrim($path, '/'));

        $this->pathCache[$path] = $viewPath;

        return $viewPath;
    }
}
