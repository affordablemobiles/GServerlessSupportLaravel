<?php

namespace A1comms\GaeSupportLaravel\View;

use InvalidArgumentException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\FileViewFinder as LaravelFileViewFinder;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Search for views in a static manifest instead of on disk,
 * hopefully resulting in less costly disk I/O.
 */
class FileViewFinder extends LaravelFileViewFinder
{
    private $manifest = [];

    public function __construct(Filesystem $files, $paths, $extensions = null, $cachePath = null)
    {
        $this->files = $files;
        $this->paths = $paths;

        if (isset($extensions)) {
            $this->extensions = $extensions;
        }

        if (!empty($cachePath)) {
            $manifestPath = $cachePath . '/manifest.php';
            if (is_file($manifestPath)) {
                $this->manifest = require $manifestPath;
            }
        }
    }

    /**
     * Find the given view in the list of paths.
     *
     * @param  string  $name
     * @param  array   $paths
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
                $viewPath = self::getRelativePath(base_path(), $path.'/'.$file);
                \Log::info("Looking for view path: " . $viewPath);
                if (!empty($this->manifest[$viewPath])) {
                    return $viewPath;
                }
            }
        }

        throw new InvalidArgumentException("View [$name] not found.");
    }

    public static function getRelativePath($from, $to, $dot = true)
    {
        return (new Filesystem())->makePathRelative($to, $from);
    }
}