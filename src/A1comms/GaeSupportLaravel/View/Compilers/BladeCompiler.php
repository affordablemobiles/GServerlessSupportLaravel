<?php

namespace A1comms\GaeSupportLaravel\View\Compilers;

use A1comms\GaeSupportLaravel\View\FileViewFinder;
use Illuminate\View\Compilers\BladeCompiler as LaravelBladeCompiler;

class BladeCompiler extends LaravelBladeCompiler
{
    /**
     * Compile the view at the given path.
     *
     * @param  string  $path
     * @return void
     */
    public function compile($path = null)
    {
        if ($path) {
            $this->setPath($path);
        }

        if (! is_null($this->cachePath)) {
            $contents = $this->compileString($this->files->get($this->getPath()));

            $compiledPath = $this->getCompiledPath($this->getRelativePath());

            $this->files->put($compiledPath, $contents);

            return $compiledPath;
        }
    }

    /**
     * Get the path to the compiled version of a view.
     *
     * @param  string  $path
     * @return string
     */
    public function getCompiledPath($path)
    {
        /**
         * Note: $path is the relative path passed through from
         *       what we returned in our custom FileViewFinder,
         *       so the SHA1 hash will be different than when
         *       outside of GAE.
         */
        return $this->cachePath.'/'.sha1($path).'.php';
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