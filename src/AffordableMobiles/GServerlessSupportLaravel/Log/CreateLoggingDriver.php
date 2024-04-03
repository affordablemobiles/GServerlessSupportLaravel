<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Log;

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

        $handler = new StreamHandler('php://stderr', Logger::INFO);
        $handler->setFormatter($formatter);

        return new Logger($logName, [$handler]);
    }
}
