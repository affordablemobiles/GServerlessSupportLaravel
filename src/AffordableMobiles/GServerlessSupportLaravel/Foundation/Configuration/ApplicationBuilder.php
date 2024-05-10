<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Foundation\Configuration;

use AffordableMobiles\GServerlessSupportLaravel\Foundation\Exceptions\Handler;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\ApplicationBuilder as LaravelApplicationBuilder;
use Illuminate\Foundation\Configuration\Exceptions;

class ApplicationBuilder extends LaravelApplicationBuilder
{
    /**
     * Register and configure the application's exception handler.
     *
     * @return $this
     */
    public function withExceptions(?callable $using = null)
    {
        $this->app->singleton(
            ExceptionHandler::class,
            Handler::class,
        );

        $using ??= static fn () => true;

        $this->app->afterResolving(
            Handler::class,
            static fn ($handler) => $using(new Exceptions($handler)),
        );

        return $this;
    }
}
