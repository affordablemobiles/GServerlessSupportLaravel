<?php

namespace A1comms\GaeSupportLaravel\Artisan;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Class SetupCommand
 *
 * @package A1comms\GaeSupportLaravel\Artisan
 */
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
    protected $description = 'Setup an App with the ability to run on Google App Engine (Standard or Flexible Environment).';

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
            $this->argument('gae-env')
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
            array('gae-env', InputArgument::REQUIRED, 'GAE Environment: std or flex.'),
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

        );
    }
}
