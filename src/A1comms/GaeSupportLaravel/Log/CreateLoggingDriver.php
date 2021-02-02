<?php

namespace A1comms\GaeSupportLaravel\Log;

use Google\Cloud\Logging\LoggingClient;
use Monolog\Formatter\NormalizerFormatter;
use Monolog\Handler\PsrHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class CreateLoggingDriver
{
    /**
     * @param array $config
     *
     * @throws \Exception
     * @return Logger
     */
    public function __invoke(array $config)
    {
        $logName = isset($config['logName']) ? $config['logName'] : 'app';

        if (is_gae_flex()) {
            $psrLogger = LoggingClient::psrBatchLogger($logName);
            $handler = new PsrHandler($psrLogger);
            $logger = new Logger($logName, [$handler]);
        } else {
            if (env('GAE_SYNC_LOGS', 'false') === 'true') {
                $psrLogger = (new LoggingClient())->psrLogger($logName);
                $handler = new PsrHandler($psrLogger);
                $logger = new Logger($logName, [$handler]);
            } else {
                // Log via structured logs in /var/log, which get dumped async into StackDriver by the runtime.
                //
                // Note:
                //
                // There is a size limit on what is allowed here per-line, so anything too big will be truncated,
                // making it invalid JSON, so it won't be parsed.
                // This means, it won't be properly tied to the request (no trace_id parsed),
                // plus, your "textPayload" will be a hardly readable, single compressed line of truncated JSON.
                $handler = new StreamHandler('/var/log/'.$logName.'.log', Logger::INFO);
                $handler->setFormatter(new JsonFormatter());
                $logger = new Logger($logName, [$handler]);
            }
        }

        return $logger;
    }
}
