<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Console;

use AffordableMobiles\GServerlessSupportLaravel\View\Compilers\BladeCompiler;
use AffordableMobiles\GServerlessSupportLaravel\View\FileViewFinder;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Deployment command for running on Google Serverless.
 */
class GServerlessViewCompileCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'g-serverless:viewcompile';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pre-Compile All Blade Views for Deployment';

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Manifest of generated files.
     *
     * @var array
     */
    protected $manifest = [];

    /**
     * Create a new view compiler command instance.
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Blade Compiler: Startup...');

        $compiledDirectory = config('view.compiled', null);
        $viewPaths         = config('view.paths', []);

        $hints = app('view')->getFinder()->getHints();
        foreach ($hints as $namespace => $paths) {
            $viewPaths = array_merge($paths, $viewPaths);
        }

        $this->info('Blade Compiler: Cleaning view storage directory ('.$compiledDirectory.')...');
        $this->files->cleanDirectory($compiledDirectory);
        $this->files->put($compiledDirectory.'/.gitkeep', ' ');
        $this->info('Blade Compiler: Cleaning view storage directory...done');

        $compiler = new BladeCompiler(app('blade.compiler'), $this->files, $compiledDirectory);

        for ($i = 0; $i < \count($viewPaths); ++$i) {
            $path         = $viewPaths[$i];
            $relativePath = FileViewFinder::getRelativePath(base_path(), $path);

            $this->info('Blade Compiler: Compiling views in '.$relativePath.' ('.($i + 1).'/'.\count($viewPaths).')...');

            $files = $this->files->allFiles($path);

            for ($g = 0; $g < \count($files); ++$g) {
                $file             = $files[$g];
                $filePath         = $file->getPathname();
                $fileRelativePath = FileViewFinder::getRelativePath(base_path(), $filePath);

                if (!preg_match('/(.*)\\.blade\\.php$/', $filePath)) {
                    $this->info("Blade Compiler: \tSkipping view (".($g + 1).'/'.\count($files).') '.$fileRelativePath);

                    continue;
                }

                $compiledPath                      = $compiler->compile($filePath);
                $this->manifest[$fileRelativePath] = FileViewFinder::getRelativePath($compiledDirectory, $compiledPath);

                $this->info("Blade Compiler: \tCompiled view (".($g + 1).'/'.\count($files).') '.$fileRelativePath);
            }

            $this->info('Blade Compiler: Compiling views in '.$relativePath.' ('.($i + 1).'/'.\count($viewPaths).')...done');
        }

        $this->writeManifest($compiledDirectory);

        return 0;
    }

    public function writeManifest($compiledDirectory): void
    {
        $this->files->put(
            $compiledDirectory.'/manifest.php',
            '<?php return '.var_export($this->manifest, true).';'.PHP_EOL
        );
    }
}
