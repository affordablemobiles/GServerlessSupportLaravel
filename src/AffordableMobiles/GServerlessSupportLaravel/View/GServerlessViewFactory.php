<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\View;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\View\Engine;
use Illuminate\Contracts\View\EngineResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\Factory as LaravelViewFactory;
use Illuminate\View\ViewFinderInterface;
use Illuminate\View\ViewFinderInterface as FinderInterface;

/**
 * Extends the default View Factory to intercept `file()` calls
 * for paths defined in the manifest's 'map', routing them
 * through the standard `make()` process to use pre-compiled views.
 * Reports attempts to use `View::file()` for unmapped Blade files.
 */
class GServerlessViewFactory extends LaravelViewFactory
{
    /**
     * Our custom view finder instance.
     *
     * @var FileViewFinder
     */
    protected $finder; // Redeclare to ensure access to getMap()

    /**
     * Cached real path of the application base path.
     *
     * @var null|false|string
     */
    private $basePathRealPath;

    /**
     * Create a new view factory instance.
     *
     * @param EngineResolver      $engines
     * @param ViewFinderInterface $finder  Must be an instance of our FileViewFinder
     *
     * @throws \InvalidArgumentException if the wrong finder type is provided
     */
    public function __construct(
        $engines,
        FinderInterface $finder, // Keep contract for compatibility
        Dispatcher $events,
        ?Container $container = null
    ) {
        // Ensure the provided finder is our custom one
        if (!$finder instanceof FileViewFinder) {
            throw new \InvalidArgumentException('GServerlessViewFactory requires an instance of '.FileViewFinder::class);
        }
        parent::__construct($engines, $finder, $events, $container);
        // $this->finder is already set by parent constructor
    }

    /**
     * Get the evaluated view contents for the given file.
     * Intercepts calls for paths defined in the manifest map.
     * Reports and falls back for unmapped Blade files.
     *
     * @param string          $path
     * @param array|Arrayable $data
     * @param array           $mergeData
     *
     * @return View
     */
    public function file($path, $data = [], $mergeData = [])
    {
        // Get the map from the finder
        // We know $this->finder is FileViewFinder due to constructor check
        $map          = $this->finder->getMap();
        $relativePath = null; // Initialize relativePath

        // Resolve the real path of the input file path
        $realPath = realpath($path);

        if (false !== $realPath && !empty($map)) {
            // Resolve and cache the application's base real path
            if (null === $this->basePathRealPath) {
                $this->basePathRealPath = realpath(base_path());
            }

            // Check if the file path is within the application base path
            if (false !== $this->basePathRealPath && Str::startsWith($realPath, $this->basePathRealPath)) {
                // Calculate path relative to base_path(), using forward slashes
                $relativePath = ltrim( // Assign to $relativePath
                    str_replace(
                        \DIRECTORY_SEPARATOR,
                        '/',
                        Str::after($realPath, $this->basePathRealPath)
                    ),
                    '/'
                );

                // Check if this relative path exists in the map
                if (isset($map[$relativePath])) {
                    $canonicalName = $map[$relativePath];

                    // Use make() with the mapped canonical name
                    return $this->make($canonicalName, $data, $mergeData);
                }
            }
        }

        // --- Fallback Logic ---
        // If we got here, the path was not found in the map (or path/map was invalid)

        // Check if it's a Blade file that wasn't mapped
        if (Str::endsWith(strtolower($path), '.blade.php')) {
            Log::warning("View::file() used for an unmapped Blade file during serverless execution. Mapping may be needed via 'g-serverless:viewcompile --map-file=...'", [
                'path'                     => $path,
                'relative_path_calculated' => $relativePath, // Include context if available
            ]);
        }

        // Proceed with the original file() behavior (using 'file' engine)
        return parent::file($path, $data, $mergeData);
    }
}
