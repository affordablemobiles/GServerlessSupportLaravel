<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Integration\Guzzle;

use GuzzleHttp\Exception\ConnectException;

class Tools
{
    public static function isConnectionError($ex, $timeout = 2)
    {
        if ($ex instanceof ConnectException) {
            $regex = '/Operation timed out after '.(string) $timeout.'[0-9]{3} milliseconds/';
            if ($timeout < 1) {
                $regex = '/Operation timed out after [0-9]{3} milliseconds/';
            }

            if (preg_match($regex, (string) $ex)) {
                return true;
            }

            return false;
        }

        return false;
    }
}
