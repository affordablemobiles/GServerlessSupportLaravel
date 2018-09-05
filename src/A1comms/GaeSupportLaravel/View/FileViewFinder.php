<?php

namespace A1comms\GaeSupportLaravel\View;

use InvalidArgumentException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\FileViewFinder as LaravelFileViewFinder;

class FileViewFinder extends LaravelFileViewFinder
{
    public function __construct(Filesystem $files, array $paths, array $extensions = null, string $cachePath = null)
    {
        $this->files = $files;
        $this->paths = $paths;

        if (isset($extensions)) {
            $this->extensions = $extensions;
        }

        if (!empty($cachePath)) {
            $manifestPath = $cachePath . '/manifest.php';
            if (is_file($manifestPath)) {
                $this->views = require $manifestPath;
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
        throw new InvalidArgumentException("View [$name] not found.");
    }
}