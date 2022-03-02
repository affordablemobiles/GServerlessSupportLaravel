<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\View\Compilers;

use A1comms\GaeSupportLaravel\View\FileViewFinder;
use Illuminate\View\Compilers\BladeCompiler as LaravelBladeCompiler;

class BladeCompiler extends LaravelBladeCompiler
{
    /**
     * Compile the view at the given path.
     *
     * @param string $path
     */
    public function compile($path = null)
    {
        if ($path) {
            $this->setPath($path);
        }

        if (null !== $this->cachePath) {
            $contents = $this->compileString($this->files->get($this->getPath()));

            $compiledPath = $this->getCompiledPath($this->getRelativePath());

            $this->files->put($compiledPath, $contents);

            return $compiledPath;
        }
    }

    /**
     * Get the path currently being compiled.
     *
     * @return string
     */
    public function getRelativePath()
    {
        return FileViewFinder::getRelativePath(base_path(), $this->path);
    }
}
