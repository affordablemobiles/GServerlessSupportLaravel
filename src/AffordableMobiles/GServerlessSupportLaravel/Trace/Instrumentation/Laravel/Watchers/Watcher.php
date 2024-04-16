<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\Laravel\Watchers;

use Illuminate\Contracts\Foundation\Application;

abstract class Watcher
{
    /**
     * Register the watcher.
     */
    abstract public function register(Application $app): void;
}
