<?php

namespace A1comms\GaeSupportLaravel\Artisan;

use Illuminate\Console\Command;
use Illuminate\Console\Application as Artisan;
use Dotenv;
use A1comms\GaeSupportLaravel\Storage\Optimizer;

/**
 * Class Configurator
 *
 * @package A1comms\GaeSupportLaravel\Artisan
 */
class Configurator
{
    protected $myCommand;

    /**
     * Constructs a new instance of Configurator class.
     *
     * @param Command $myCommand console
     * command to be used for console output.
     */
    public function __construct(Command $myCommand)
    {
        $this->myCommand = $myCommand;
    }

    /**
     * Configures a Laravel app to be deployed on GAE.
     *
     */
    public function configure()
    {
        $env_file               = app_path().'/../.env';
        $env_production_file    = app_path().'/../.env.production';
        $env_local_file         = app_path().'/../.env.local';
        $bootstrap_app_php      = app_path().'/../bootstrap/app.php';
        $config_app_php         = app_path().'/../config/app.php';
        $config_view_php        = app_path().'/../config/view.php';
        $config_filesystems_php = app_path().'/../config/filesystems.php';
        $cached_config_php      = base_path().'/bootstrap/cache/config.php';

        $this->processFile($bootstrap_app_php, ['replaceAppClass']);
        $this->processFile($config_app_php, ['replaceLaravelServiceProviders']);
        $this->processFile($config_view_php, ['replaceCompiledPath']);
        $this->processFile($config_filesystems_php, ['addGaeDisk']);
    }

    /**
     * Processes a given file with given processors.
     *
     * @param  string $filePath   the path of the file to be processed.
     * @param  array  $processors array of processor function names to
     * be called during the file processing. Every such function shall
     * receive the file contents string as a parameter and return the
     * modified file contents.
     *
     * <code>
     * protected function processorFunc($contents)
     * {
     *     ...
     *     return $modified;
     * }
     * </code>
     */
    protected function processFile($filePath, $processors)
    {
        $contents = file_get_contents($filePath);

        $processed = $contents;

        foreach ($processors as $processor) {
            $processed = $this->$processor($processed);
        }

        if ($processed === $contents) {
            return;
        }

        $this->backupFile($filePath);

        file_put_contents($filePath, $processed);
    }

    /**
     * Processor function. Replaces the Laravel
     * application class with the one compatible with GAE.
     *
     * @param string $contents the 'bootstrap/app.php' file contents.
     *
     * @return string the modified file contents.
     */
    protected function replaceAppClass($contents)
    {
        $modified = str_replace(
            'Illuminate\Foundation\Application',
            'A1comms\GaeSupportLaravel\Foundation\Application',
            $contents);

        $modified = str_replace(
            'Laravel\Lumen\Application',
            'A1comms\GaeSupportLaravel\Foundation\LumenApplication',
            $contents);

        if ($contents !== $modified) {
            $this->myCommand->info('Replaced the application class in "bootstrap/app.php".');
        }

        return $modified;
    }

    /**
     * Processor function. Replaces the Laravel
     * service providers with GAE compatible ones.
     *
     * @param string $contents the 'config/app.php' file contents.
     *
     * @return string the modified file contents.
     */
    protected function replaceLaravelServiceProviders($contents)
    {
        $strings = [
            'Illuminate\View\ViewServiceProvider',
        ];

        // Replacement to:
        //  - replace Blade compiler isExpired method for better cachefs support.
        $replacements = [
            'A1comms\GaeSupportLaravel\View\ViewServiceProvider',
        ];

        $modified = str_replace($strings, $replacements, $contents);

        if ($contents !== $modified) {
            $this->myCommand->info('Replaced the service providers in "config/app.php".');
        }

        return $modified;
    }

    /**
     * Processor function. Replaces 'compiled' path with GAE
     * compatible one when running on GAE.
     *
     * @param string $contents the 'config/view.php' file contents.
     * @return string the modified file contents.
     */
    protected function replaceCompiledPath($contents)
    {
        $expression = "/'compiled'\s*=>\s*(.+(,|(?=\s+\]))|[^,]+(,|(?=\s+\])))/";
        $replacement =
<<<EOT
'compiled' => env('CACHE_COMPILED_VIEWS') ?
                  'REPLACE_WITH_VIEW_PATH' :
                  storage_path('framework/views'),
EOT;

        $modified = preg_replace($expression, $replacement, $contents);

        if ($contents !== $modified) {
            $this->myCommand->info('Replaced the \'compiled\' path in "config/view.php".');
        }

        return $modified;
    }

    /**
     * Adds the GAE disk configuration to the 'config/filesystem.php'
     * if it does not already exist.
     *
     * @param string $contents the 'config/filesystem.php' file contents.
     * @return string the modified file contents.
     */
    protected function addGaeDisk($contents)
    {
        if (str_contains($contents, "'gae'")) {
            return $contents;
        }

        $expressions = [
            "/'default'.*=>\s*'\b.+\b'/",
            "/'disks'\s*=>\s*\[/"
        ];

        $replacements = [
            "'default' => env('FILESYSTEM', 'local')",
<<<EOT
'disks' => [

		'gae' => [
			'driver' => 'gae',
			'root'   => storage_path().'/app',
		],
EOT
        ];

        $modified = preg_replace($expressions, $replacements, $contents);

        if ($contents !== $modified) {
            $this->myCommand->info('Added GAE filesystem driver configuration in "config/filesystems.php".');
        }

        return $modified;
    }

    /**
     * Creates a backup copy of a desired file.
     *
     * @param string $filePath the file path.
     * @return string the created backup file path.
     */
    protected function backupFile($filePath)
    {
        $sourcePath = $filePath;
        $backupPath = $filePath.'.bak';

        if (file_exists($backupPath)) {
            $date = new \DateTime();
            $backupPath = "{$filePath}{$date->getTimestamp()}.bak";
        }

        copy($sourcePath, $backupPath);

        return $backupPath;
    }

    /**
     * Restores a file from its backup copy.
     *
     * @param string $filePath the file path.
     * @param string $backupPath the backup path.
     * @param boolean $clean if 'true' deletes the backup copy.
     * @return string the created backup file path.
     */
    protected function restoreFile($filePath, $backupPath, $clean = true)
    {
        if (file_exists($backupPath)) {
            copy($backupPath, $filePath);

            if ($clean) {
                unlink($backupPath);
            }
        }

        return $backupPath;
    }
}
