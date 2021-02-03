<?php

namespace A1comms\GaeSupportLaravel\View\Compilers;

use InvalidArgumentException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\CompilerInterface;

class FakeCompiler implements CompilerInterface
{

    /**
     * Get the cache path for the compiled views.
     *
     * @var string
     */
    protected $cachePath;

    /**
     * Create a new compiler instance.
     *
     * @param  string  $cachePath
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($cachePath)
    {
        if (! $cachePath) {
            throw new InvalidArgumentException('Please provide a valid cache path.');
        }

        $this->cachePath = $cachePath;
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
     * Determine if the view at the given path is expired.
     *
     * @param  string  $path
     * @return bool
     */
    public function isExpired($path)
    {
        return false;
    }

    /**
     * Don't actually compile,
     * we're a fake!
     */
    public function compile($path)
    {
        return false;
    }

    /**
     * Register a handler for custom directives.
     *
     * @param  string  $name
     * @param  callable  $handler
     * @return void
     */
    public function directive($name, callable $handler)
    {
    }

    /**
     * Get the list of custom directives.
     *
     * @return array
     */
    public function getCustomDirectives()
    {
        return [];
    }
}
