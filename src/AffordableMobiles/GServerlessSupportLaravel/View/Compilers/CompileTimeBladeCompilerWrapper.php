<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\View\Compilers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler as LaravelBladeCompiler;

/**
 * Compile-time wrapper for the real BladeCompiler.
 * Used by the GServerlessViewCompileCommand to compile views during deployment.
 * It handles saving the compiled file using the standard hashing mechanism
 * but facilitates getting the hashed filename for the manifest.
 */
class CompileTimeBladeCompilerWrapper
{
    protected LaravelBladeCompiler $compiler;
    protected Filesystem $files;
    // Note: cachePath is implicitly handled by the injected $compiler instance

    /**
     * Constructor.
     *
     * @param LaravelBladeCompiler $compiler the original Laravel Blade Compiler instance (properly configured with cache path)
     * @param Filesystem           $files    filesystem instance
     */
    public function __construct(LaravelBladeCompiler $compiler, Filesystem $files)
    {
        $this->compiler = $compiler;
        $this->files    = $files;
    }

    /**
     * Compile the view at the given absolute path.
     * Saves the compiled file using the standard BladeCompiler logic (path derived from view name hash).
     * Returns the basename of the compiled file (the hashed filename).
     *
     * @param string $absolutePath the absolute path to the source Blade file
     * @param string $viewName     The canonical name of the view (e.g., 'posts.index').
     *
     * @return string The basename of the compiled file (e.g., 'sha1(...).php').
     *
     * @throws \RuntimeException if compilation fails
     */
    public function compile(string $absolutePath, string $viewName): string
    {
        try {
            // Set the path on the original compiler (important for error reporting during compile)
            $this->compiler->setPath($absolutePath);

            // Get the raw content of the Blade file
            $contents = $this->files->get($absolutePath);

            // Compile the content using the original compiler's logic
            $compiledContents = $this->compiler->compileString($contents);

            // Determine the destination path using the VIEW NAME hash
            // This uses the original compiler's configured cache path.
            $compiledPath = $this->compiler->getCompiledPath($viewName); // e.g., cache/sha1(posts.index).php

            // Ensure the cache directory exists
            // Use dirname() as ensureDirectoryExists expects the directory path itself.
            $this->files->ensureDirectoryExists(\dirname($compiledPath));

            // Save the compiled content
            $this->files->put($compiledPath, $compiledContents);

            // Return only the basename (hashed filename) for the manifest
            return basename($compiledPath);
        } catch (\Throwable $e) { // Catch any potential error/exception
            // Throw a new exception, providing context and including the original exception
            throw new \RuntimeException(
                "Failed to compile Blade view '{$viewName}' ({$absolutePath}): ".$e->getMessage(),
                $e->getCode(), // Use original exception code
                $e // Pass the original exception
            );
        } finally {
            // Ensure path is reset on the compiler instance if it's reused in a loop
            $this->compiler->setPath(null);
        }
    }
}
