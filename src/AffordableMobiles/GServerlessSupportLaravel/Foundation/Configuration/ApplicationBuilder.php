<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Foundation\Configuration;

use AffordableMobiles\GServerlessSupportLaravel\Integration\ErrorReporting\Report;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\ApplicationBuilder as LaravelApplicationBuilder;
use Illuminate\Foundation\Configuration\Exceptions;

class ApplicationBuilder extends LaravelApplicationBuilder
{
    /**
     * Get the application instance.
     *
     * @return Application
     */
    public function create()
    {
        if (is_g_serverless()) {
            $this->withExceptions(static function (Exceptions $exceptions): void {
                $exceptions->report(static function (\Throwable $e): void {
                    Report::exceptionHandler($e);
                })->stop();
            });
        }

        return $this->app;
    }
}
