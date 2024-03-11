<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Exception;

class InvalidTokenException extends \Exception
{
    public function __construct($message = null, $code = 0, \Exception $previous = null)
    {
        return parent::__construct($message, $code, $previous);
    }
}
