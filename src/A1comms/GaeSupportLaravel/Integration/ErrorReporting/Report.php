<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Integration\ErrorReporting;

use A1comms\GaeSupportLaravel\Log\CreateLoggingDriver;
use Google\Cloud\Logging\LoggingClient;
use Google\Cloud\Logging\PsrLogger;

/**
 * Static methods for bootstrapping Stackdriver Error Reporting.
 */
class Report
{
    public const DEFAULT_LOGNAME = 'exception';

    /** @var PsrLogger */
    public static $psrLogger;

    /**
     * Register hooks for error reporting.
     *
     * @codeCoverageIgnore
     */
    public static function init(?PsrLogger $psrLogger = null): void
    {
        $options = ['batchEnabled' => false];

        if (is_cloud_run()) {
            self::$psrLogger = (new CreateLoggingDriver())([
                'logName'   => self::DEFAULT_LOGNAME,
                'formatter' => 'exception',
            ]);
        } else {
            self::$psrLogger = $psrLogger ?: (new LoggingClient())
                ->psrLogger(self::DEFAULT_LOGNAME, $options)
            ;
        }

        register_shutdown_function([self::class, 'shutdownHandler']);
        set_exception_handler([self::class, 'exceptionHandler']);
        set_error_handler([self::class, 'errorHandler']);
    }

    /**
     * Return a string prefix for the given error level.
     *
     * @param int $level
     *
     * @return string a string prefix for reporting the error
     */
    public static function getErrorPrefix($level)
    {
        switch ($level) {
            case E_PARSE:
                $prefix = 'PHP Parse error';

                break;

            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
                $prefix = 'PHP Fatal error';

                break;

            case E_USER_ERROR:
            case E_RECOVERABLE_ERROR:
                $prefix = 'PHP error';

                break;

            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                $prefix = 'PHP Warning';

                break;

            case E_NOTICE:
            case E_USER_NOTICE:
                $prefix = 'PHP Notice';

                break;

            case E_STRICT:
                $prefix = 'PHP Debug';

                break;

            default:
                $prefix = 'PHP Notice';
        }

        return $prefix;
    }

    /**
     * Return an error level string for the given PHP error level.
     *
     * @param int $level
     *
     * @return string an error level string
     */
    public static function getErrorLevelString($level)
    {
        switch ($level) {
            case E_PARSE:
                return 'CRITICAL';

            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
            case E_RECOVERABLE_ERROR:
                return 'ERROR';

            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                return 'WARNING';

            case E_NOTICE:
            case E_USER_NOTICE:
                return 'NOTICE';

            case E_STRICT:
                return 'DEBUG';

            default:
                return 'NOTICE';
        }
    }

    public static function exceptionHandler(\Throwable $ex, int $status_code = 500, array $context = []): void
    {
        $message = \sprintf('PHP Notice: %s', (string) $ex);
        if (self::$psrLogger) {
            $service = gae_service();
            $version = gae_version();
            self::$psrLogger->error($message, [
                'context' => array_merge($context, [
                    'reportLocation' => [
                        'filePath'     => $ex->getFile(),
                        'lineNumber'   => $ex->getLine(),
                        'functionName' => self::getFunctionNameForReport($ex->getTrace()),
                    ],
                    'httpRequest' => [
                        'method'             => empty($_SERVER['REQUEST_METHOD']) ?: $_SERVER['REQUEST_METHOD'],
                        'url'                => (empty($_SERVER['HTTP_HOST']) ? '' : $_SERVER['HTTP_HOST']).(empty($_SERVER['REQUEST_URI']) ? '' : $_SERVER['REQUEST_URI']),
                        'userAgent'          => empty($_SERVER['HTTP_USER_AGENT']) ? '' : $_SERVER['HTTP_USER_AGENT'],
                        'referrer'           => empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER'],
                        'responseStatusCode' => $status_code,
                        'remoteIp'           => empty($_SERVER['REMOTE_ADDR']) ? '' : $_SERVER['REMOTE_ADDR'],
                    ],
                    'user' => self::getUserNameForReport(),
                ]),
                'serviceContext' => [
                    'service' => $service,
                    'version' => $version,
                ],
            ]);
        }
        // fwrite(STDERR, $message . PHP_EOL);
    }

    /**
     * @param int    $level   the error level
     * @param string $message the error message
     * @param string $file    the filename that the error was raised in
     * @param int    $line    the line number that the error was raised at
     */
    public static function errorHandler(int $level, string $message, string $file, int $line): bool
    {
        if (!($level & error_reporting())) {
            return true;
        }
        $message = \sprintf(
            '%s: %s in %s on line %d',
            self::getErrorPrefix($level),
            $message,
            $file,
            $line
        );
        if (!self::$psrLogger) {
            return false;
        }
        $service = gae_service();
        $version = gae_version();
        $context = [
            'context' => [
                'reportLocation' => [
                    'filePath'     => $file,
                    'lineNumber'   => $line,
                    'functionName' => self::getFunctionNameForReport(),
                ],
                'httpRequest' => [
                    'method'             => empty($_SERVER['REQUEST_METHOD']) ?: $_SERVER['REQUEST_METHOD'],
                    'url'                => (empty($_SERVER['HTTP_HOST']) ? '' : $_SERVER['HTTP_HOST']).(empty($_SERVER['REQUEST_URI']) ? '' : $_SERVER['REQUEST_URI']),
                    'userAgent'          => empty($_SERVER['HTTP_USER_AGENT']) ? '' : $_SERVER['HTTP_USER_AGENT'],
                    'referrer'           => empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER'],
                    'responseStatusCode' => null,
                    'remoteIp'           => empty($_SERVER['REMOTE_ADDR']) ? '' : $_SERVER['REMOTE_ADDR'],
                ],
                'user' => self::getUserNameForReport(),
            ],
            'serviceContext' => [
                'service' => $service,
                'version' => $version,
            ],
        ];
        self::$psrLogger->log(
            self::getErrorLevelString($level),
            $message,
            $context
        );

        return true;
    }

    /**
     * Called at exit, to check there's a fatal error and report the error if
     * any.
     */
    public static function shutdownHandler(): void
    {
        if ($err = error_get_last()) {
            switch ($err['type']) {
                case E_ERROR:
                case E_PARSE:
                case E_COMPILE_ERROR:
                case E_CORE_ERROR:
                    $service = gae_service();
                    $version = gae_version();
                    $message = \sprintf(
                        '%s: %s in %s on line %d',
                        self::getErrorPrefix($err['type']),
                        $err['message'],
                        $err['file'],
                        $err['line']
                    );
                    $context = [
                        'context' => [
                            'reportLocation' => [
                                'filePath'     => $err['file'],
                                'lineNumber'   => $err['line'],
                                'functionName' => self::getFunctionNameForReport(),
                            ],
                        ],
                        'serviceContext' => [
                            'service' => $service,
                            'version' => $version,
                        ],
                    ];
                    if (self::$psrLogger) {
                        self::$psrLogger->log(
                            self::getErrorLevelString($err['type']),
                            $message,
                            $context
                        );
                    }

                    break;
            }
        }
    }

    /**
     * Format the function name from a stack trace. This could be a global
     * function (function_name), a class function (Class->function), or a static
     * function (Class::function).
     *
     * @param array $trace The stack trace returned from Exception::getTrace()
     */
    private static function getFunctionNameForReport(?array $trace = null)
    {
        if (null === $trace) {
            return '<unknown function>';
        }
        if (empty($trace[0]['function'])) {
            return '<none>';
        }
        $functionName = [$trace[0]['function']];
        if (isset($trace[0]['type'])) {
            $functionName[] = $trace[0]['type'];
        }
        if (isset($trace[0]['class'])) {
            $functionName[] = $trace[0]['class'];
        }

        return implode('', array_reverse($functionName));
    }

    private static function getUserNameForReport()
    {
        if (\defined('ERROR_REPORTING_USER')) {
            return ERROR_REPORTING_USER;
        }

        return empty($_SERVER['HTTP_X_GOOG_AUTHENTICATED_USER_EMAIL']) ? null : str_replace('accounts.google.com:', '', $_SERVER['HTTP_X_GOOG_AUTHENTICATED_USER_EMAIL']);
    }
}
