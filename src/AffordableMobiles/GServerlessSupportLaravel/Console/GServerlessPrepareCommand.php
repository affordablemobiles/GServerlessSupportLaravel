<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Console;

use AffordableMobiles\GServerlessSupportLaravel\Foundation\ProviderRepository;
use AffordableMobiles\GServerlessSupportLaravel\View\ViewServiceProvider;
use Illuminate\Console\Command;

/**
 * Deployment command for running on Google Serverless.
 */
class GServerlessPrepareCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'g-serverless:prepare';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deployment command for Google Cloud Serverless';

    /**
     * Prefix for all console logs.
     *
     * @var string
     */
    protected $logPrefix = 'Google Serverless Deployment: ';

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
            $this->call('g-serverless:viewcompile');
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
