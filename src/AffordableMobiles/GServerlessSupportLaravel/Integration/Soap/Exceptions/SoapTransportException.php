<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Integration\Soap\Exceptions;

class SoapTransportException extends \SoapFault
{
    public function __construct(string $message, string $code = 'HTTP', ?\Throwable $previous = null)
    {
        parent::__construct($code, $message);

        if (null !== $previous) {
            $prop = new \ReflectionProperty(\Exception::class, 'previous');
            $prop->setAccessible(true);
            $prop->setValue($this, $previous);
        }
    }
}
