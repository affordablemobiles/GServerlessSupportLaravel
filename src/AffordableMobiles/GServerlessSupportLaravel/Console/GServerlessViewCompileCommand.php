<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Console;

use AffordableMobiles\GServerlessSupportLaravel\View\Compilers\CompileTimeBladeCompilerWrapper;
use Illuminate\Console\Command;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Pre-compiles all Blade views using canonical names for deployment.
 * Includes handling for specific files/directories mapped via CLI options.
 */
class GServerlessViewCompileCommand extends Command
{
    /** Public constant for health check view name */
    public const HEALTH_CHECK_VIEW_NAME = '__laravel_health_check__';

    /** Public constant for health check relative path */
    public const HEALTH_CHECK_SOURCE_PATH_RELATIVE = 'vendor/laravel/framework/src/Illuminate/Foundation/resources/health-up.blade.php';

    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'g-serverless:viewcompile
        {--map-file=* : Map a specific file path (relative to base_path, use /) to a canonical name (e.g., path/to/file.blade.php:canonical_name)}
        {--map-dir=* : Map a directory (relative to base_path, use /) to a namespace (e.g., path/to/dir:namespace)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pre-Compile All Blade Views using canonical names for Deployment, including mapped files/dirs.';

    /** @var Filesystem */
    protected $files;

    /** @var CompileTimeBladeCompilerWrapper */
    protected $compilerWrapper;

    /** @var Factory */
    protected $viewFactory;

    /**
     * Data structure to be written to manifest.php.
     *
     * @var array{map: array<string, string>, views: array<string, string>}
     */
    protected $manifestData = ['map' => [], 'views' => []];

    /** @var array<string, bool> */
    protected $processedPaths = [];

    /**
     * Create a new command instance.
     */
    public function __construct(
        Filesystem $files,
        CompileTimeBladeCompilerWrapper $compilerWrapper,
        ViewFactory $viewFactory
        // Removed ConfigRepository
    ) {
        parent::__construct();
        $this->files           = $files;
        $this->compilerWrapper = $compilerWrapper;
        $this->viewFactory     = $viewFactory;
        // Removed config assignment
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->line('Pre-compiling Blade views...');
        $compiledDirectory = config('view.compiled');
        if (!$compiledDirectory) {
            $this->error('View compiled path not configured (config view.compiled).');

            return Command::FAILURE;
        }

        // 1. Clean directory
        $this->info("Cleaning view storage directory: {$compiledDirectory}");
        if ($this->files->isDirectory($compiledDirectory)) {
            $this->files->cleanDirectory($compiledDirectory);
            $this->files->put($compiledDirectory.'/.gitkeep', '');
            $this->info('Cleaned view storage directory.');
        } else {
            $this->files->makeDirectory($compiledDirectory, 0o777, true, true);
            $this->info('Created view storage directory.');
        }

        // 2. Initialize and process mappings
        $this->manifestData   = ['map' => [], 'views' => []];
        $this->processedPaths = [];
        $fileMap              = $this->buildFileMapFromOptions();

        // 3. Compile views
        // Compile explicitly mapped files/dirs first (from CLI options + default health check)
        $this->compileMappedViews($fileMap);

        // Compile standard namespaced views (avoiding already processed paths)
        $this->compileNamespaceViews();

        // Compile standard default views (avoiding already processed paths)
        $this->compileDefaultViews();

        // 4. Write manifest
        if (empty($this->manifestData['views'])) {
            $this->warn('No views were found or compiled.');
        } else {
            $this->info('Compiled '.\count($this->manifestData['views']).' views.');
        }
        $this->writeManifest($compiledDirectory);
        $this->info('Blade manifest generated successfully.');

        $this->line('View compilation complete.');

        return Command::SUCCESS;
    }

    /**
     * Write the view manifest file.
     *
     * @param string $compiledDirectory the directory to write the manifest into
     *
     * @throws \RuntimeException if writing the manifest fails
     */
    public function writeManifest(string $compiledDirectory): void
    {
        $filePath = $compiledDirectory.'/manifest.php';
        $this->files->ensureDirectoryExists(\dirname($filePath));
        // Write the combined structure
        $content = '<?php return '.var_export($this->manifestData, true).';'.PHP_EOL;
        if (false === $this->files->put($filePath, $content)) {
            throw new \RuntimeException("Failed to write view manifest file: {$filePath}");
        }
    }

    /**
     * Build the file map from CLI options, including the default health check.
     *
     * @return array<string, string> Map of relative path => canonical name
     */
    protected function buildFileMapFromOptions(): array
    {
        $map = [];
        // Add default health check
        $map[self::HEALTH_CHECK_SOURCE_PATH_RELATIVE] = self::HEALTH_CHECK_VIEW_NAME;

        // Process --map-file options
        foreach ($this->option('map-file') as $mapFile) {
            if (false === strpos($mapFile, ':')) {
                $this->warn("Invalid --map-file format: {$mapFile}. Skipping. Use path:name format.");

                continue;
            }
            [$relativePath, $canonicalName] = explode(':', $mapFile, 2);
            // Normalize path separator just in case
            $map[str_replace('\\', '/', $relativePath)] = $canonicalName;
        }

        // Process --map-dir options (will be handled in compileMappedViews)
        // We just return the file map here. Directories are handled separately.
        return $map;
    }

    /**
     * Compile views explicitly mapped via CLI options (files and directories).
     *
     * @param array<string, string> $fileMap Map of relative file path => canonical name
     */
    protected function compileMappedViews(array $fileMap): void
    {
        $this->info('Compiling explicitly mapped views from CLI options...');

        // Compile mapped files
        foreach ($fileMap as $relativePath => $canonicalName) {
            $this->compileSingleMappedFile($relativePath, $canonicalName);
        }

        // Compile mapped directories
        foreach ($this->option('map-dir') as $mapDir) {
            if (false === strpos($mapDir, ':')) {
                $this->warn("Invalid --map-dir format: {$mapDir}. Skipping. Use path:namespace format.");

                continue;
            }
            [$relativeDirPath, $namespace] = explode(':', $mapDir, 2);
            $systemRelativeDirPath         = str_replace('/', \DIRECTORY_SEPARATOR, $relativeDirPath);
            $absoluteDirPath               = base_path($systemRelativeDirPath);

            if ($this->files->isDirectory($absoluteDirPath)) {
                $this->info("Compiling mapped directory [{$relativeDirPath}] as namespace [{$namespace}]");
                // Use compileViewsFromPath, treating it like a namespace
                $this->compileViewsFromPath($absoluteDirPath, $namespace);
            } else {
                $this->warn(" > Mapped directory not found or is not a directory: {$absoluteDirPath}. Skipping.");
            }
        }
    }

    /**
     * Compile a single file specified in the map.
     */
    protected function compileSingleMappedFile(string $relativePath, string $canonicalName): void
    {
        $systemRelativePath = str_replace('/', \DIRECTORY_SEPARATOR, $relativePath);
        $absolutePath       = base_path($systemRelativePath);

        if (!$this->files->exists($absolutePath)) {
            $this->warn(" > Mapped view file not found: {$absolutePath} (from relative: {$relativePath}). Skipping.");

            return;
        }

        $realAbsolutePath = realpath($absolutePath);
        if (false === $realAbsolutePath) {
            $this->warn(" > Could not resolve real path for mapped view: {$absolutePath}. Skipping.");

            return;
        }

        if (isset($this->processedPaths[$realAbsolutePath])) {
            $this->line(" > Mapped view already processed: {$relativePath}. Skipping explicit compilation.");

            return;
        }

        $this->line(" > Compiling mapped view [{$canonicalName}] from {$relativePath}");

        try {
            $hashedFilename = $this->compilerWrapper->compile($absolutePath, $canonicalName);

            if (!empty($hashedFilename)) {
                // Add to both parts of the manifest
                $this->manifestData['map'][$relativePath]    = $canonicalName; // Use normalized relative path
                $this->manifestData['views'][$canonicalName] = $hashedFilename;
                $this->processedPaths[$realAbsolutePath]     = true;
                $this->line("   - Compiled [{$canonicalName}]");
            } else {
                $this->warn("   - Compilation returned empty (but no error) for [{$canonicalName}]");
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Failed to compile mapped Blade view [{$canonicalName}] from {$relativePath}: ".$e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Compile views registered under namespaces (hints).
     */
    protected function compileNamespaceViews(): void
    {
        $finder = $this->viewFactory->getFinder();
        $hints  = $finder->getHints();

        foreach ($hints as $namespace => $paths) {
            $this->info("Compiling views for namespace: [{$namespace}]");
            foreach ($paths as $path) {
                if ($this->files->isDirectory($path)) {
                    $this->compileViewsFromPath($path, $namespace);
                } else {
                    $this->warn(" > Path does not exist or is not a directory: {$path}");
                }
            }
        }
    }

    /**
     * Compile views from default paths (config('view.paths')).
     */
    protected function compileDefaultViews(): void
    {
        $paths = config('view.paths', []); // Still use config for default view paths
        $this->info('Compiling views from default paths...');
        foreach ($paths as $path) {
            if ($this->files->isDirectory($path)) {
                $this->compileViewsFromPath($path, null); // null namespace
            } else {
                $this->warn(" > Path does not exist or is not a directory: {$path}");
            }
        }
    }

    /**
     * Compile all blade views found within a given path. (Used by namespace/default/map-dir loops)
     * Skips files if their real path has already been processed.
     *
     * @param string      $path      the potentially unresolved path to scan
     * @param null|string $namespace the namespace for these views, or null if none
     *
     * @throws \RuntimeException if compilation of a view fails
     */
    protected function compileViewsFromPath(string $path, ?string $namespace): void
    {
        $realBasePath = realpath($path);
        if (false === $realBasePath) {
            $this->warn(" > Could not resolve real path for: {$path}. Skipping.");

            return;
        }

        $allFiles = $this->files->allFiles($realBasePath);
        $files    = array_filter($allFiles, static fn (SplFileInfo $file) => Str::endsWith($file->getFilename(), '.blade.php'));

        $total = \count($files);
        $count = 0;
        $this->line(" > Found {$total} Blade files in ".($namespace ? "{$namespace}::" : '').$realBasePath);

        foreach ($files as $file) {
            ++$count;
            $absolutePath = $file->getRealPath();
            if (false === $absolutePath) {
                $this->warn("   - Could not get real path for file: {$file->getPathname()}. Skipping.");

                continue;
            }

            // Skip if already processed (e.g., via explicit map or overlapping path)
            if (isset($this->processedPaths[$absolutePath])) {
                continue;
            }

            $canonicalName = $this->generateCanonicalName($realBasePath, $absolutePath, $namespace);
            if (!$canonicalName) {
                $this->warn("   - Could not determine canonical name for [{$absolutePath}] relative to base [{$realBasePath}].");

                continue;
            }

            try {
                $hashedFilename = $this->compilerWrapper->compile($absolutePath, $canonicalName);
                if (!empty($hashedFilename)) {
                    // Only add to 'views' part, 'map' is only for explicitly mapped View::file() targets
                    $this->manifestData['views'][$canonicalName] = $hashedFilename;
                    $this->processedPaths[$absolutePath]         = true;
                    $this->line("   - Compiled [{$canonicalName}] ({$count}/{$total})");
                } else {
                    $this->warn("   - Compilation returned empty (but no error) for [{$canonicalName}] ({$count}/{$total})");
                }
            } catch (\Throwable $e) {
                throw new \RuntimeException(
                    "Failed to compile Blade view [{$canonicalName}]: ".$e->getMessage(),
                    $e->getCode(),
                    $e
                );
            }
        }
    }

    /**
     * Generate the canonical view name from file paths.
     *
     * @param string      $realBasePath the resolved, absolute base view path the file was found in
     * @param string      $absolutePath The absolute path to the .blade.php file.
     * @param null|string $namespace    the namespace, if applicable
     *
     * @return null|string the canonical name, or null on error
     */
    protected function generateCanonicalName(string $realBasePath, string $absolutePath, ?string $namespace): ?string
    {
        $realBasePath = rtrim($realBasePath, \DIRECTORY_SEPARATOR).\DIRECTORY_SEPARATOR;
        if (!Str::startsWith($absolutePath, $realBasePath)) {
            return null;
        }
        $relativePath = Str::after($absolutePath, $realBasePath);
        $namePart     = str_replace(
            [\DIRECTORY_SEPARATOR, '.blade.php'],
            ['.', ''],
            $relativePath
        );

        return $namespace ? "{$namespace}::{$namePart}" : $namePart;
    }

    /**
     * Get the console command options.
     * Override to define array options correctly.
     */
    protected function getOptions(): array
    {
        return [
            ['map-file', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Map a specific file path (relative to base_path, use /) to a canonical name (e.g., path/to/file.blade.php:canonical_name)'],
            ['map-dir', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Map a directory (relative to base_path, use /) to a namespace (e.g., path/to/dir:namespace)'],
        ];
    }
}
