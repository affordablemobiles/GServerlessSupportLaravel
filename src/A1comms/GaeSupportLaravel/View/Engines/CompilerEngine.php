<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\View\Engines;

use Illuminate\View\Engines\CompilerEngine as LaravelCompilerEngine;
use Throwable;

class CompilerEngine extends LaravelCompilerEngine
{
    /**
     * Get the evaluated contents of the view.
     *
     * @param string $path
     *
     * @return string
     */
    public function get($path, array $data = [])
    {
        $this->lastCompiled[] = $path;

        $compiled = $this->compiler->getCompiledPath($path);

        // Once we have the path to the compiled file, we will evaluate the paths with
        // typical PHP just like any other templates. We also keep a stack of views
        // which have been rendered for right exception messages to be generated.
        $results = $this->evaluatePath($compiled, $data);

        array_pop($this->lastCompiled);

        return $results;
    }

    /**
     * Get the exception message for an exception.
     *
     * @return string
     */
    protected function getMessage(Throwable $e)
    {
        return $e->getMessage().' (View: '.last($this->lastCompiled).')';
    }
}
