<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Log;

use Google\Cloud\Logging\LoggingClient;
use Monolog\Handler\PsrHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class CreateLoggingDriver
{
    /**
     * @return Logger
     *
     * @throws \Exception
     */
    public function __invoke(array $config)
    {
        $logName = $config['logName'] ?? 'app';

        $formatter = new JsonFormatter();
        if (isset($config['formatter'])) {
            switch ($config['formatter']) {
                case 'exception':
                    $formatter = new ExceptionJsonFormatter();

                    break;

                default:
                    $formatter = new JsonFormatter();

                    break;
            }
        }

        if (is_cloud_run()) {
            $handler = new StreamHandler('/tmp/logpipe', Logger::INFO);
            $handler->setFormatter($formatter);
            $logger = new Logger($logName, [$handler]);
        } elseif (is_gae_std()) {
            if ('true' === env('GAE_SYNC_LOGS', 'false')) {
                $psrLogger = (new LoggingClient())->psrLogger($logName);
                $handler   = new PsrHandler($psrLogger);
                $logger    = new Logger($logName, [$handler]);
            } else {
                // Log via structured logs in /var/log, which get dumped async into StackDriver by the runtime.
                //
                // Note:
                //
                // There is a size limit on what is allowed here per-line, so anything too big will be truncated,
                // making it invalid JSON, so it won't be parsed.
                // This means, it won't be properly tied to the request (no trace_id parsed),
                // plus, your "textPayload" will be a hardly readable, single compressed line of truncated JSON.
                $handler = new StreamHandler('/var/log/app.log', Logger::INFO);
                $handler->setFormatter($formatter);
                $logger = new Logger($logName, [$handler]);
            }
        } else {
            $handler = new StreamHandler('php://stderr', Logger::INFO);
            $handler->setFormatter($formatter);
            $logger = new Logger($logName, [$handler]);
        }

        return $logger;
    }
}
