<?php

namespace A1comms\GaeSupportLaravel\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;

/**
 * Still implement this so it'll generate some other files
 * that reside in bootstrap/cache at build time,
 * but don't actually compile & cache the app config.
 */
class ConfigCacheCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'config:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a cache file for faster configuration loading';

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Create a new config cache command instance.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->call('config:clear');

        $config = $this->getFreshConfiguration();

        $this->warn('Configuration cache disabled!');
    }

    /**
     * Boot a fresh copy of the application configuration.
     *
     * @return array
     */
    protected function getFreshConfiguration()
    {
        $app = require $this->laravel->bootstrapPath().'/app.php';

        $app->make(ConsoleKernelContract::class)->bootstrap();

        return $app['config']->all();
    }
}
