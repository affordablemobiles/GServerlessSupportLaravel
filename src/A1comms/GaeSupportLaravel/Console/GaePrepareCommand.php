<?php

namespace A1comms\GaeSupportLaravel\Console;

use Illuminate\Console\Command;
use A1comms\GaeSupportLaravel\View\ViewServiceProvider;
use A1comms\GaeSupportLaravel\Foundation\ProviderRepository;

/**
 * Deployment command for running on GAE.
 */
class GaePrepareCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'gae:prepare';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deployment command for production App Engine';


    /**
     * Prefix for all console logs.
     *
     * @var string
     */
    protected $logPrefix = 'App Engine Deployment: ';

    /**
     * Create a new config cache command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->info($this->logPrefix . "Starting...");

        $this->call('config:clear');

        $this->runViewCompiler();

        $this->runRefreshManifest();

        $this->info($this->logPrefix . "Ready to Deploy!");
    }

    public function runViewCompiler()
    {
        if (in_array(ViewServiceProvider::class, config('app.providers'))) {
            $this->info($this->logPrefix . "Pre-Compiled View Provider active, compiling views...");
            $this->call('gae:viewcompile');
            $this->info($this->logPrefix . "Pre-Compiled View Provider active, compiling views...done");
        }
    }

    public function runRefreshManifest()
    {
        $this->info($this->logPrefix . "Generating provider manifest...");
        (new ProviderRepository())->preCompileManifest();
        $this->info($this->logPrefix . "Generating provider manifest...done");
    }
}
