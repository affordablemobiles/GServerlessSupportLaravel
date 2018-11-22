<?php

namespace A1comms\GaeSupportLaravel\Log;

use Google\Cloud\Logging\LoggingClient;
use Monolog\Handler\PsrHandler;
use Monolog\Handler\SyslogHandler;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Formatter\LineFormatter;

class Logger
{
    public static function setup($app)
    {
        $handler = self::getHandler();

        if (is_null($handler)) {
            return;
        } else {
            $app->configureMonologUsing(function ($monolog) {
                $monolog->pushHandler($handler);
            });
        }
    }

    public static function getHandler()
    {
        if ((!is_gae()) || (php_sapi_name() == 'cli'))
        {
            return;
        }

        if (GAE_LEGACY)
        {
            $handler = new SyslogHandler('laravel');
            $handler->setFormatter(new LineFormatter('%message% %context% %extra%'));
            return $handler;
        }
        else if (is_gae_flex())
        {
            $logging = new LoggingClient();
            return new PsrHandler($logging->psrLogger('app', ['batchEnabled' => true]));
        }
        else
        {
            return new ErrorLogHandler()
        }
    }
}