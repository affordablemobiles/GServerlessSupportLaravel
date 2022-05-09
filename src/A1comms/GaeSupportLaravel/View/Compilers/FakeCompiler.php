<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\View\Compilers;

use Illuminate\View\Compilers\Compiler;
use Illuminate\View\Compilers\CompilerInterface;
use InvalidArgumentException;

class FakeCompiler extends Compiler implements CompilerInterface
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
            throw new InvalidArgumentException('Please provide a valid cache path.');
        }

        $this->cachePath = $cachePath;
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
    public function compile($path)
    {
        return false;
    }

    /**
     * Register a handler for custom directives.
     *
     * @param string $name
     */
    public function directive($name, callable $handler): void
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
