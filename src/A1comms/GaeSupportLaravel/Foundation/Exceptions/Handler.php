<?php

namespace A1comms\GaeSupportLaravel\Foundation\Exceptions;

use Throwable;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use A1comms\GaeSupportLaravel\Integration\ErrorReporting\Report as ErrorBootstrap;

class Handler extends ExceptionHandler
{
    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function report(Throwable $exception)
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
