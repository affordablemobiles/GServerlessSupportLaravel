<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Foundation;

use \Illuminate\Foundation\Configuration\ApplicationBuilder as LaravelApplicationBuilder;
use Illuminate\Foundation\Configuration\Exceptions;

class ApplicationBuilder extends LaravelApplicationBuilder
{
    /**
     * Get the application instance.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function create()
    {
        $this->withExceptions(function (Exceptions $exceptions) {
            $exceptions->report(function (\Throwable $e) {
                \AffordableMobiles\GServerlessSupportLaravel\Integration\ErrorReporting\Report::exceptionHandler($e);
            })->stop();
        });

        return $this->app;
    }
}
