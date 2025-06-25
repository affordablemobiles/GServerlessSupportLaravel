<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\View;

use AffordableMobiles\GServerlessSupportLaravel\View\Exceptions\RuntimeCompilationNotSupportedException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
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
     *
     * @var array<string, string>
     */
    protected array $manifestMap = [];

    /**
     * A map of dynamic runtime namespaces to their compile-time counterparts.
     */
    protected array $dynamicNamespaceMap = [];

    /**
     * Create a new file view loader instance.
     *
     * @param array       $paths      Original view paths
     * @param null|array  $extensions View extensions
     * @param null|string $cachePath  path to the directory containing the manifest
     */
    public function __construct(Filesystem $files, array $paths, ?array $extensions = null, ?string $cachePath = null)
    {
        // We must call parent constructor to properly initialize the default finder.
        parent::__construct($files, $paths, $extensions);

        if (!empty($cachePath)) {
            $manifestPath = rtrim($cachePath, '/\\').'/manifest.php';

            if ($this->files->exists($manifestPath)) {
                $manifestContent = @require $manifestPath;

                if (!\is_array($manifestContent)) {
                    throw new \RuntimeException("View manifest file is invalid or corrupt: {$manifestPath}");
                }
                $this->manifestMap         = $manifestContent['map']                ?? [];
                $this->manifestViews       = $manifestContent['views']              ?? [];
                $this->dynamicNamespaceMap = $manifestContent['dynamic_namespaces'] ?? [];
            }
        }
    }

    /**
     * Find the given view name.
     * Checks the pre-compiled Blade manifest first, then falls back to the parent
     * filesystem finder for non-Blade views.
     *
     * @param string $name the view name
     *
     * @return string the path to the view
     *
     * @throws \InvalidArgumentException if the view is not found in the manifest or on disk
     */
    public function find($name): string
    {
        $lookupName = $name;

        // Attempt to resolve dynamic Blade namespaces first.
        if (str_contains($name, '::')) {
            [$namespace, $view] = $this->parseNamespaceSegments($name);

            if (isset($this->dynamicNamespaceMap[$namespace])) {
                $currentAbsolutePaths = $this->hints[$namespace] ?? [];
                $basePath             = app()->basePath().'/';
                $currentRelativePaths = array_map(static fn ($path) => Str::after($path, $basePath), $currentAbsolutePaths);

                foreach ($this->dynamicNamespaceMap[$namespace] as $compileTimeNamespace => $compileTimeRelativePaths) {
                    if ($currentRelativePaths === $compileTimeRelativePaths) {
                        $lookupName = $compileTimeNamespace.'::'.$view;

                        break;
                    }
                }
            }
        }

        $normalizedName = str_replace('/', '.', $lookupName);

        // If the view is in our Blade manifest, return the "fake" path.
        if (isset($this->manifestViews[$normalizedName])) {
            return $normalizedName.'.blade.php';
        }

        // If not in the manifest, it's either a non-Blade view or a Blade view
        // that was missed during compilation. Delegate to the parent finder.
        try {
            return parent::find($name);
        } catch (RuntimeCompilationNotSupportedException $e) {
            // This custom exception means a Blade file was found on disk but was not in our manifest.
            // This is the most specific and helpful error.
            throw new \InvalidArgumentException("View [{$name}] (resolved to [{$normalizedName}]) not found in pre-compiled manifest.", 0, $e);
        } catch (\InvalidArgumentException $e) {
            // The parent finder throws this when no view file (.php, .css, etc.) is found on disk.
            throw new \InvalidArgumentException("View [{$name}] (resolved to [{$normalizedName}]) could not be found in the pre-compiled manifest or on disk.", 0, $e);
        }
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
     *
     * @return array<string, string>
     */
    public function getMap(): array
    {
        return $this->manifestMap;
    }

    /**
     * Get the loaded view compilation data (canonicalName => hashedFilename).
     *
     * @return array<string, string>
     */
    public function getManifestViews(): array
    {
        return $this->manifestViews;
    }
}
