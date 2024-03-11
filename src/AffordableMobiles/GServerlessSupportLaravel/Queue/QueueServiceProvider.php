<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Queue;

use Illuminate\Queue\QueueServiceProvider as LaravelQueueServiceProvider;

class QueueServiceProvider extends LaravelQueueServiceProvider
{
    /**
     * Register the connectors on the queue manager.
     *
     * @param \Illuminate\Queue\QueueManager $manager
     */
    public function registerConnectors($manager): void
    {
        parent::registerConnectors($manager);
        $this->registerGaeConnector($manager);
    }

    /**
     * Register the GAE queue connector.
     *
     * @param \Illuminate\Queue\QueueManager $manager
     */
    protected function registerGaeConnector($manager): void
    {
        $app = $this->app;

        $manager->addConnector('gae', static fn () => new GaeConnector($app['encrypter'], $app['request']));
    }
}
