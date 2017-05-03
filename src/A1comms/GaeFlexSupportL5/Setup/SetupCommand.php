<?php

namespace A1comms\GaeFlexSupportL5\Setup;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class SetupCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'gae:setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add Google App Engine Flexible Environment support to the application.';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        $configurator = new Configurator($this);
        $configurator->configure(
            $this->option('cache-config'),
            $this->option('local-dev')
        );
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
        );
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('cache-config', null, InputOption::VALUE_NONE,
                'Generate cached Laravel config file for use on Google App Engine.', null),
            array('local-dev', null, InputOption::VALUE_NONE,
                'Revert the .env.local file back to .env for local development.', null),
        );
    }
}
