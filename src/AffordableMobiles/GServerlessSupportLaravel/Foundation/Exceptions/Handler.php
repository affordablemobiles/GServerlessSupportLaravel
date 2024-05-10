<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Foundation\Exceptions;

use AffordableMobiles\GServerlessSupportLaravel\Integration\ErrorReporting\Report;
use Illuminate\Foundation\Exceptions\Handler as LaravelHandler;
use Illuminate\Support\Arr;
use Illuminate\Support\Reflector;
use Psr\Log\LogLevel;

class Handler extends LaravelHandler
{
    /**
     * Reports error based on report method on exception or to logger.
     *
     * @throws \Throwable
     */
    protected function reportThrowable(\Throwable $e): void
    {
        $this->reportedExceptionMap[$e] = true;

        if (Reflector::isCallable($reportCallable = [$e, 'report'])
            && false !== $this->container->call($reportCallable)) {
            return;
        }

        foreach ($this->reportCallbacks as $reportCallback) {
            if ($reportCallback->handles($e) && false === $reportCallback($e)) {
                return;
            }
        }

        $level = Arr::first(
            $this->levels,
            static fn ($level, $type) => $e instanceof $type,
            LogLevel::ERROR
        );

        $context = $this->buildExceptionContext($e);

        $unthrown = $this->isUnthrownReport(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

        Report::exceptionHandler($e, $unthrown ? 200 : 500, $context, $level);
    }

    private function isUnthrownReport(array $backtrace = []): bool
    {
        $function = $backtrace[2]['function'] ?? '';
        $class    = $backtrace[2]['class']    ?? '';

        if ('report' === $function && '' === $class) {
            return true;
        }

        return false;
    }
}
