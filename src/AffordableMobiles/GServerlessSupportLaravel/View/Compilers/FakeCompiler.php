<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\View\Compilers;

use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Compilers\Compiler;
use Illuminate\View\Compilers\CompilerInterface;

class FakeCompiler extends BladeCompiler implements CompilerInterface
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
     * @param string $cachePath
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($cachePath)
    {
        if (!$cachePath) {
            throw new \InvalidArgumentException('Please provide a valid cache path.');
        }

        $this->cachePath = $cachePath;
        $this->basePath  = '';
    }

    /**
     * Determine if the view at the given path is expired.
     *
     * @param string $path
     *
     * @return bool
     */
    public function isExpired($path)
    {
        return false;
    }

    /**
     * Don't actually compile,
     * we're a fake!
     *
     * @param mixed $path
     */
    public function compile($path = null)
    {
        return false;
    }

    /**
     * Compile the given Blade template contents.
     *
     * @param string $value
     *
     * @return string
     */
    public function compileString($value)
    {
        throw new \Exception('dynamic compile unsupported');
    }

    /**
     * Register a handler for custom directives.
     *
     * @param string $name
     */
    public function directive($name, callable $handler, bool $bind = false): void {}

    /**
     * Register an "if" statement directive.
     *
     * @param string $name
     */
    public function if($name, callable $callback): void {}

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
