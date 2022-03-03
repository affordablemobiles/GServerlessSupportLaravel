<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Console;

use A1comms\GaeSupportLaravel\Foundation\ProviderRepository;
use A1comms\GaeSupportLaravel\View\ViewServiceProvider;
use Illuminate\Console\Command;

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
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info($this->logPrefix.'Starting...');

        $this->call('config:clear');

        $this->runViewCompiler();

        $this->runRefreshManifest();

        $this->info($this->logPrefix.'Ready to Deploy!');

        return 0;
    }

    public function runViewCompiler(): void
    {
        if (\in_array(ViewServiceProvider::class, config('app.providers'), true)) {
            $this->info($this->logPrefix.'Pre-Compiled View Provider active, compiling views...');
            $this->call('gae:viewcompile');
            $this->info($this->logPrefix.'Pre-Compiled View Provider active, compiling views...done');
        }
    }

    public function runRefreshManifest(): void
    {
        $this->info($this->logPrefix.'Generating provider manifest...');
        (new ProviderRepository())->preCompileManifest();
        $this->info($this->logPrefix.'Generating provider manifest...done');
    }
}
