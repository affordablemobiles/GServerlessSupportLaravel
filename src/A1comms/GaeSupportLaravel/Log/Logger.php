<?php

namespace A1comms\GaeSupportLaravel\Log;

use Google\Cloud\Logging\LoggingClient;
use Monolog\Logger as MonologLogger;
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
            if (env('GAE_SYNC_LOGS', 'false') === 'true') {
                $app->configureMonologUsing(function ($monolog) {
                    $logging = new LoggingClient();
                    $monolog->pushHandler(new PsrHandler($logging->psrLogger('app')));
                });
            } else {
                // Log via structured logs in /var/log, which get dumped async into StackDriver by the runtime.
                //
                // Note:
                //
                // There is a size limit on what is allowed here per-line, so anything too big will be truncated,
                // making it invalid JSON, so it won't be parsed.
                // This means, it won't be properly tied to the request (no trace_id parsed),
                // plus, your "textPayload" will be a hardly readable, single compressed line of truncated JSON.
                $app->configureMonologUsing(function ($monolog) {
                    $handler = new StreamHandler('/var/log/app.log', MonologLogger::INFO);
                    $handler->setFormatter(new JsonFormatter());
                    $monolog->pushHandler($handler);
                });
            }
        }
    }
}