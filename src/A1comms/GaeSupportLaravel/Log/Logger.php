<?php

namespace A1comms\GaeSupportLaravel\Log;

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
            $app->configureMonologUsing(function ($monolog) {
                $logging = new LoggingClient();
                $monolog->pushHandler(new PsrHandler($logging->psrLogger('app')));
            });
        }
    }
}