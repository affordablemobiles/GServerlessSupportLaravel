<?php

namespace A1comms\GaeSupportLaravel\Foundation\Exceptions;

use Exception;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use A1comms\GaeSupportLaravel\Integration\ErrorReporting\Report as ErrorBootstrap;

class LumenHandler extends ExceptionHandler
{
    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
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
