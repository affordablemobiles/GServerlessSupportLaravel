<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Foundation;

use AffordableMobiles\GServerlessSupportLaravel\Log\LogServiceProvider;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Log\Context\ContextServiceProvider;
use Illuminate\Routing\RoutingServiceProvider;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

class Application extends LaravelApplication
{
    /**
     * Begin configuring a new Laravel application instance.
     *
     * @return Configuration\ApplicationBuilder
     */
    public static function configure(?string $basePath = null)
    {
        $basePath = match (true) {
            \is_string($basePath) => $basePath,
            default               => static::inferBasePath(),
        };

        return (new Configuration\ApplicationBuilder(new static($basePath)))
            ->withKernels()
            ->withEvents()
            ->withCommands()
            ->withProviders()
        ;
    }

    /**
     * Handle the incoming Artisan command.
     *
     * @return int
     */
    public function handleCommand(InputInterface $input)
    {
        $kernel = $this->make(ConsoleKernelContract::class);

        $status = $kernel->handle(
            $input,
            new ConsoleOutput(ConsoleOutput::VERBOSITY_VERY_VERBOSE)
        );

        $kernel->terminate($input, $status);

        return $status;
    }

    /**
     * Register all of the base service providers.
     */
    protected function registerBaseServiceProviders(): void
    {
        $this->register(new EventServiceProvider($this));
        $this->register(new LogServiceProvider($this));
        $this->register(new ContextServiceProvider($this));
        $this->register(new RoutingServiceProvider($this));
    }
}
