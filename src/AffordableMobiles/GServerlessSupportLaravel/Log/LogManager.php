<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Log;

use AffordableMobiles\GServerlessSupportLaravel\Log\Formatter\JsonFormatter;
use Illuminate\Log\Logger;
use Illuminate\Log\LogManager as LaravelLogManager;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as Monolog;
use Psr\Log\LoggerInterface;

/**
 * @mixin \Illuminate\Log\Logger
 */
class LogManager extends LaravelLogManager
{
    /**
     * Create an emergency log handler to avoid white screens of death.
     *
     * @return LoggerInterface
     */
    protected function createEmergencyLogger()
    {
        if (!is_g_serverless()) {
            return parent::createEmergencyLogger();
        }

        $formatter = new JsonFormatter();

        $handler = new StreamHandler(
            'php://stderr',
            $this->level(['level' => 'debug']),
        );
        $handler->setFormatter($formatter);

        return new Logger(
            new Monolog('emergency', $this->prepareHandlers([$handler])),
            $this->app['events']
        );
    }
}
