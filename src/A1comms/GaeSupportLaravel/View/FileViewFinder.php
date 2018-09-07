<?php

namespace A1comms\GaeSupportLaravel\View;

use InvalidArgumentException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\FileViewFinder as LaravelFileViewFinder;
/**
 * Search for views in a static manifest instead of on disk,
 * hopefully resulting in less costly disk I/O.
 */
class FileViewFinder extends LaravelFileViewFinder
{
    private $manifest = [];

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
                if (!empty($this->manifest[$viewPath])) {
                    return $viewPath;
                }
            }
        }

        throw new InvalidArgumentException("View [$name] not found.");
    }

    public static function getRelativePath($from, $to, $dot = true)
    {
        $from     = explode('/', $from);
        $to       = explode('/', $to);
        $relPath  = $to;

        foreach($from as $depth => $dir) {
            // find first non-matching dir
            if($dir === $to[$depth]) {
                // ignore this directory
                array_shift($relPath);
            } else {
                // get number of remaining dirs to $from
                $remaining = count($from) - $depth;
                if($remaining > 1) {
                    // add traversals up to first matching dir
                    $padLength = (count($relPath) + $remaining - 1) * -1;
                    $relPath = array_pad($relPath, $padLength, '..');
                    break;
                } else {
                    if ($dot) {
                        $relPath[0] = './' . $relPath[0];
                    }
                }
            }
        }

        return implode('/', $relPath);
    }
}