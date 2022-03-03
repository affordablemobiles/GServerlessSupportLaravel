<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Foundation\Exceptions;

use A1comms\GaeSupportLaravel\Integration\ErrorReporting\Report as ErrorBootstrap;
use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

class Handler extends ExceptionHandler
{
    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     */
    public function report(Throwable $exception): void
    {
        parent::report($exception);

        // Log the error out to Stackdriver Error Reporting.
        if ($this->shouldReport($exception)) {
            try {
                ErrorBootstrap::exceptionHandler($exception);
            } catch (Exception $ex) {
            }
        }
    }
}
