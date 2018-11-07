<?php

namespace A1comms\GaeSupportLaravel\Log;

use Google\Cloud\Logging\LoggingClient;
use Monolog\Logger;
use Monolog\Handler\PsrHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\ErrorLogHandler;

class Logger
{
    public static function setup($app)
    {
        if ((!is_gae()) || (php_sapi_name() == 'cli'))
        {
            return;
        }

        if (is_gae_flex())
        {
            $app->configureMonologUsing(function ($monolog) {
                $logging = new LoggingClient();
                $monolog->pushHandler(new PsrHandler($logging->psrLogger('app', ['batchEnabled' => true])));
            });
        }
        else
        {
            /*$app->configureMonologUsing(function ($monolog) {
                $logging = new LoggingClient();
                $monolog->pushHandler(new PsrHandler($logging->psrLogger('app')));
            });*/

            // Proper logging isn't yet supported on App Engine 7.2 runtime.
            // Just log here using PHP, so it goes via stderr, until structured logging
            // via /var/log files is available.
            $app->configureMonologUsing(function ($monolog) {
                $monolog->pushHandler(new StreamHandler('/var/log/app.log', Logger::INFO));
            });
        }
    }
}