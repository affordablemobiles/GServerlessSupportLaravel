<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\View\Compilers;

use Illuminate\Support\Str;
use Illuminate\View\Compilers\CompilerInterface;

/**
 * A compiler implementation that performs NO compilation at runtime.
 * It relies on a pre-generated manifest (views part) and the runtime cache path
 * to find already compiled views. Used in production serverless environments.
 */
class FakeCompiler implements CompilerInterface
{
    /**
     * The 'views' portion of the manifest: [canonicalName => hashedFilename.php].
     *
     * @var array<string, string>
     */
    protected array $manifestViews;

    /**
     * The configured cache path for compiled views at runtime.
     */
    protected string $runtimeCachePath;

    /**
     * Create a new fake compiler instance.
     *
     * @param array  $manifestViews    The 'views' part of the manifest [canonicalName => hashedFilename.php].
     * @param string $runtimeCachePath The runtime value of config('view.compiled').
     */
    public function __construct(array $manifestViews, string $runtimeCachePath)
    {
        $this->manifestViews    = $manifestViews;
        $this->runtimeCachePath = rtrim($runtimeCachePath, '/\\'); // Normalize path
    }

    /**
     * Determine if the view is expired. Always returns false.
     *
     * @param string $name the view name
     *
     * @return bool always false
     */
    public function isExpired($name): bool
    {
        return false;
    }

    /**
     * Get the path to the compiled version of a view.
     * Extracts the canonical name from the fake path (name + .blade.php),
     * looks up the name in the manifest's 'views' data to get the hashed filename,
     * then prepends the runtime cache path.
     *
     * @param string $path The fake path (name + .blade.php) passed by CompilerEngine.
     *
     * @return string the full path to the compiled file for the current runtime environment
     *
     * @throws \InvalidArgumentException if the extracted view name is not in the manifest's 'views' data
     */
    public function getCompiledPath($path): string // Parameter is the fake path now
    {
        // Extract canonical name from fake path
        $name = $path;
        // Check if the path ends with '.blade.php' and strip it if it does
        if (Str::endsWith($path, '.blade.php')) {
            $name = Str::beforeLast($path, '.blade.php');
        }

        // Look up the extracted canonical name in the manifest
        if (isset($this->manifestViews[$name])) {
            $hashedFilename = $this->manifestViews[$name];

            // Construct the full path using the runtime cache directory
            return $this->runtimeCachePath.'/'.$hashedFilename;
        }

        // Throw exception if the extracted name wasn't found
        // Use the extracted name in the error message for clarity
        throw new \InvalidArgumentException("Compiled path for view [{$name}] (derived from path [{$path}]) not found in manifest views.");
    }

    /**
     * Compile the view. Throws an exception as runtime compilation is disabled.
     *
     * @param string $name the view name
     *
     * @throws \RuntimeException always
     */
    public function compile($name): void
    {
        throw new \RuntimeException('Runtime Blade compilation is disabled in this environment.');
    }

    /**
     * Get the Blade extensions.
     */
    public function getExtensions(): array
    {
        return ['blade.php'];
    }
}
