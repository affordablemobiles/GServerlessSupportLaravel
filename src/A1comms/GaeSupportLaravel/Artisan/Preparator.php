<?php

namespace A1comms\GaeSupportLaravel\Artisan;

use Illuminate\Console\Command;
use Artisan;
use Dotenv;

/**
 * Class Preparator
 *
 * @package A1comms\GaeSupportLaravel\Artisan
 */
class Preparator
{
    protected $myCommand;

    /**
     * Constructs a new instance of Preparator class.
     *
     * @param Command $myCommand console
     * command to be used for console output.
     */
    public function __construct(Command $myCommand)
    {
        $this->myCommand = $myCommand;
    }

    /**
     * Prepares a Laravel app to be deployed on GAE.
     *
     * @param string $gaeEnv the GAE environment type: std, flex or local (development env).
     */
    public function prepare($gaeEnv)
    {
        if ( ! in_array($gaeEnv, ['std', 'flex', 'local', 'dev']) )
        {
            $this->myCommand->error('Invalid GAE Environment type, must be either "std", "flex", "local" or "dev".');
            return;
        }

        $env_file               = app_path().'/../env';
        $env_production_file    = app_path().'/../env.production';
        $env_local_file         = app_path().'/../env.local';

        $cached_config_php      = base_path().'/bootstrap/cache/config.php';

        if ($gaeEnv == 'local') {
            $this->moveEnvForLocal($env_file, $env_production_file, $env_local_file);

            Dotenv::makeMutable();
            Dotenv::load(dirname($env_file), basename($env_file));

            Artisan::call('config:cache', array());
        } else if ($gaeEnv == 'dev') {
            $this->moveEnvForLocal($env_file, $env_production_file, $env_local_file);

            Dotenv::makeMutable();
            Dotenv::load(dirname($env_file), basename($env_file));

            $result = Artisan::call('config:cache', array());
            if ($result === 0) {
                $this->processFile($cached_config_php, ['fixCachedConfig']);
            }
        } else {
            $this->moveEnvForDeploy($env_file, $env_production_file, $env_local_file);

            Dotenv::makeMutable();
            Dotenv::load(dirname($env_file), basename($env_file));

            $result = Artisan::call('config:cache', array());
            if ($result === 0) {
                $this->processFile($cached_config_php, ['fixCachedConfig']);
            }
        }

    }

    /**
     * Move the .env.production file to .env ready for deployment.
     *
     * @param string $env_file path to the .env file.
     * @param string $env_production_file path to the .env.production file.
     * @param string $env_local_file path to the .env.local file.
     */
    protected function moveEnvForDeploy($env_file, $env_production_file, $env_local_file)
    {
        if ( is_file($env_production_file) ) {
            $this->myCommand->info('Moving .env.production to .env ready for deployment.');
            rename($env_file, $env_local_file);
            rename($env_production_file, $env_file);
        }
    }

    /**
     * Move the .env.local file to .env ready for local development.
     *
     * @param string $env_file path to the .env file.
     * @param string $env_production_file path to the .env.production file.
     * @param string $env_local_file path to the .env.local file.
     */
    protected function moveEnvForLocal($env_file, $env_production_file, $env_local_file)
    {
        if ( is_file($env_local_file) ) {
            $this->myCommand->info('Moving .env.local to .env ready for local development.');
            rename($env_file, $env_production_file);
            rename($env_local_file, $env_file);
        }
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

        file_put_contents($filePath, $processed);
    }

    /**
     * Determines whether the app is running on windows.
     * @return boolean 'true' if running on Windows,  otherwise 'false'.
     */
    protected function isRunningOnWindows()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return true;
        }

        return false;
    }

    /**
     * Processor function. Pre-processes windows paths.
     *
     * @param string $contents the 'bootstrap/cache/config.php' file contents.
     *
     * @return string the modified file contents.
     */
    protected function preprocessWindowsPaths($contents)
    {
        $expression = "/'([A-Za-z]:)?((\\\\|\/)[^\\/:*?\"\'<>|\r\n]*)*'/";

        $paths = array();
        preg_match_all($expression, $contents, $paths);

        $modified = $contents;
        foreach ($paths[0] as $path) {
            $normalizedPath = str_replace('\\\\', '/', $path);
            $modified = str_replace($path, $normalizedPath, $modified);
        }

        if ($contents !== $modified) {
            $this->myCommand->info('Preprocessed windows paths in "bootstrap/cache/config.php".');
        }

        return $modified;
    }

    /**
     * Fixes the paths in the cached config file.
     *
     * @param string $contents the 'bootstrap/cache/config.php' file contents.
     * @return string the modified file contents.
     */
    protected function fixCachedConfig($contents)
    {
        $app_path = app_path();
        $storage_path = storage_path();
        $base_path = base_path();
        $replaceFunction = 'str_replace';

        if ($this->isRunningOnWindows()) {
            $contents = $this->preprocessWindowsPaths($contents);
            $app_path     = str_replace('\\', '/', $app_path);
            $storage_path = str_replace('\\', '/', $storage_path);
            $base_path    = str_replace('\\', '/', $base_path);
            $replaceFunction = 'str_ireplace';
        }

        $strings = [
            "'${app_path}",
            "'${storage_path}",
            "'${base_path}",
            "'REPLACE_WITH_VIEW_PATH'"
        ];

        $replacements = [
            "app_path().'",
            "storage_path().'",
            "base_path().'",
            '\A1comms\GaeSupportLaravel\Storage\Optimizer::compiledViewsPath()'
        ];

        $modified = $replaceFunction($strings, $replacements, $contents);

        if ($contents !== $modified) {
            $this->myCommand->info('Generated "bootstrap/cache/config.php" for GAE deployment.');
            $this->myCommand->comment('* To use "bootstrap/cache/config.php" locally please run "php artisan gae:prepare local".');
        }

        return $modified;
    }
}
