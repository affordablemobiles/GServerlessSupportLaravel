<?php

namespace A1comms\GaeSupportLaravel\Integration\Guzzle;

use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Facades\Log;

class Tools
{
    private static function isConnectionError($ex, $timeout = 2)
    {
        if ($ex instanceof ConnectException) {
            if ($timeout < 1) {
                $regex = "/Operation timed out after [0-9]{3} milliseconds/";    
            }
            $regex = "/Operation timed out after " . (string)$timeout . "[0-9]{3} milliseconds/";

            if (preg_match($regex, (string)$ex)) {
                return true;
            }

            return false;
        }

        return false;
    }
}
