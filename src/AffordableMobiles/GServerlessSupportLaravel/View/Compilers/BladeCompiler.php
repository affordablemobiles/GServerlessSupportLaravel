<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\View\Compilers;

use AffordableMobiles\GServerlessSupportLaravel\View\FileViewFinder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler as LaravelBladeCompiler;

class BladeCompiler
{
    public function __construct(protected LaravelBladeCompiler $compiler, protected Filesystem $files, protected string $cachePath)
    {
        $rpfiles = new \ReflectionProperty($this->compiler, 'files');
        $rpfiles->setAccessible(true);
        $rpfiles->setValue($this->compiler, $files);

        $rpcache = new \ReflectionProperty($this->compiler, 'cachePath');
        $rpcache->setAccessible(true);
        $rpcache->setValue($this->compiler, $cachePath);
    }

    /**
     * Compile the view at the given path.
     *
     * @param string $path
     */
    public function compile($path = null)
    {
        if ($path) {
            $this->compiler->setPath($path);
        }

        if (null !== $this->cachePath) {
            $contents = $this->compiler->compileString($this->files->get($this->compiler->getPath()));

            $compiledPath = $this->compiler->getCompiledPath($this->getRelativePath());

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
        return FileViewFinder::getRelativePath(base_path(), $this->compiler->getPath());
    }
}
