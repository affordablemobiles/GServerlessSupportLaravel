<?php

namespace A1comms\GaeSupportLaravel\Artisan;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Class PrepareCommand
 *
 * @package A1comms\GaeSupportLaravel\Artisan
 */
class PrepareCommand extends Command
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
    protected $description = 'Prepare App to run on Google App Engine (Standard or Flexible Environment).';

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
        $preparator = new Preparator($this);
        $preparator->prepare(
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
            array('gae-env', InputArgument::REQUIRED, 'GAE Environment: std, flex or local (development).'),
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
