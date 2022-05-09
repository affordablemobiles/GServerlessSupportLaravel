<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Auth\Exception;

use Exception;

class InvalidTokenException extends Exception
{
    public function __construct($message = null, $code = 0, Exception $previous = null)
    {
        return parent::__construct($message, $code, $previous);
    }
}
