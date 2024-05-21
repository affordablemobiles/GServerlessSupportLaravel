<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Integration\Guzzle;

use GuzzleHttp\Exception\ConnectException;

class Tools
{
    public static function isConnectionError(\Throwable $ex, float $timeout = 2)
    {
        if ($ex instanceof ConnectException) {
            $regex = self::getRegexp($timeout < 1 ? '' : (string) $timeout);

            return 1 === preg_match($regex, (string) $ex);
        }

        return false;
    }

    protected static function getRegexp(string $timeout): string
    {
        return '/(Operation|Connection) (timed out|timeout) after '.$timeout.'[0-9]{3} (ms|milliseconds)/';
    }
}
