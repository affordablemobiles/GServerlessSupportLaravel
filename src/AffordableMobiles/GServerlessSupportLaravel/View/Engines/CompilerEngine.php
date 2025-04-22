<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\View\Engines;

use Illuminate\Support\Str;
use Illuminate\View\Compilers\CompilerInterface;
use Illuminate\View\Engines\CompilerEngine as LaravelCompilerEngine;

class CompilerEngine extends LaravelCompilerEngine
{
    /**
     * Create a new compiler engine instance.
     */
    public function __construct(CompilerInterface $compiler) // Ensure constructor matches parent
    {
        parent::__construct($compiler);
    }

    /**
     * Get the exception message for an exception.
     * Overrides the parent method to display the canonical view name.
     */
    protected function getMessage(\Throwable $e): string
    {
        // Get the last view path pushed onto the stack (our "fake" path)
        $viewPath = last($this->lastCompiled) ?: 'Unknown';

        // Extract the canonical name by removing the .blade.php suffix
        $canonicalName = $viewPath;
        if (Str::endsWith($viewPath, '.blade.php')) {
            $canonicalName = Str::beforeLast($viewPath, '.blade.php');
        }

        // Construct the message using the canonical name
        return $e->getMessage().' (View: '.$canonicalName.')';
    }
}
