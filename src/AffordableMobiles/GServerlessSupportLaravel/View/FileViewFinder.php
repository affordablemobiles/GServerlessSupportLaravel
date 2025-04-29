<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\View;

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\FileViewFinder as LaravelFileViewFinder;

/**
 * Finds views using a static manifest containing compiled view mappings.
 * Skips filesystem lookups at runtime for performance in serverless environments.
 */
class FileViewFinder extends LaravelFileViewFinder
{
    /**
     * The 'views' portion of the manifest: [canonicalName => hashedFilename.php].
     *
     * @var array<string, string>
     */
    protected array $manifestViews = [];

    /**
     * The 'map' portion of the manifest: [relativePath => canonicalName].
     * Used for View::file() lookups.
     *
     * @var array<string, string>
     */
    protected array $manifestMap = [];

    /**
     * The path where the manifest file is located.
     */
    protected ?string $manifestPath = null;

    /**
     * Create a new file view loader instance.
     *
     * @param array       $paths      Original view paths (not used for runtime lookup)
     * @param null|array  $extensions View extensions
     * @param null|string $cachePath  path to the directory containing the manifest
     *
     * @throws \RuntimeException if the manifest file is missing or invalid when expected
     */
    public function __construct(Filesystem $files, array $paths, ?array $extensions = null, ?string $cachePath = null)
    {
        $this->files         = $files;
        $this->paths         = $paths;
        $this->extensions    = $extensions ?? ['blade.php', 'php', 'css'];
        $this->hints         = [];
        $this->manifestViews = []; // Initialize
        $this->manifestMap   = [];   // Initialize

        if (!empty($cachePath)) {
            $this->manifestPath = rtrim($cachePath, '/\\').'/manifest.php';

            if ($this->files->exists($this->manifestPath)) {
                $manifestContent = @require $this->manifestPath;

                // Validate the structure of the loaded manifest
                if (\is_array($manifestContent) && isset($manifestContent['map'], $manifestContent['views']) && \is_array($manifestContent['map']) && \is_array($manifestContent['views'])) {
                    $this->manifestMap   = $manifestContent['map'];
                    $this->manifestViews = $manifestContent['views'];
                } else {
                    throw new \RuntimeException("View manifest file is invalid or corrupt (expecting 'map' and 'views' arrays): {$this->manifestPath}");
                }
            } else {
                throw new \RuntimeException("View manifest file not found: {$this->manifestPath}");
            }
        }
    }

    /**
     * Find the given view name by checking the 'views' part of the manifest.
     * Normalizes the input name (converts '/' to '.') before lookup.
     * Returns a "fake" path ending in .blade.php to satisfy the ViewFactory's
     * engine resolution logic.
     *
     * @param string $name The view name, potentially using '/' or '.' as separator.
     *
     * @return string A fake path combining the normalized name and '.blade.php'.
     *
     * @throws \InvalidArgumentException if the view name is not found in the manifest after normalization
     */
    public function find($name): string
    {
        $normalizedName = str_replace('/', '.', trim($name));

        // Use the normalized name for the lookup in the 'views' part of the manifest
        if (isset($this->manifestViews[$normalizedName])) {
            // Return the normalized name + .blade.php
            // This allows ViewFactory::getEngineFromPath to detect the 'blade' engine
            return $normalizedName.'.blade.php';
        }

        // Throw exception if the normalized name wasn't found
        throw new \InvalidArgumentException("View [{$normalizedName}] (normalized from [{$name}]) not found in pre-compiled manifest views.");
    }

    /**
     * Returns the canonical view name from a compiled path.
     *
     * @param string $compiledPath
     *
     * @return null|string the canonical view name if found
     */
    public function reverseFind($compiledPath): ?string
    {
        return array_flip($this->manifestViews)[$compiledPath] ?? null;
    }

    /**
     * Get the loaded file map data (relativePath => canonicalName).
     * Useful for injecting into the GServerlessViewFactory.
     *
     * @return array<string, string>
     */
    public function getMap(): array
    {
        return $this->manifestMap;
    }

    /**
     * Get the loaded view compilation data (canonicalName => hashedFilename).
     * Kept for potential direct use, though FakeCompiler uses it now.
     *
     * @return array<string, string>
     */
    public function getManifestViews(): array
    {
        return $this->manifestViews;
    }
}
