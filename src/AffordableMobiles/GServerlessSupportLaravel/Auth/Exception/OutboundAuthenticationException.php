<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Exception;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

class OutboundAuthenticationException extends \RuntimeException
{
    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        protected readonly ?string $driver = null,
        protected readonly null|string|UriInterface $targetUri = null,
        protected readonly ?string $tokenEndpoint = null,
        protected readonly ?ResponseInterface $response = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getDriver(): ?string
    {
        return $this->driver;
    }

    public function getTargetUri(): ?string
    {
        return null !== $this->targetUri ? (string) $this->targetUri : null;
    }

    public function getTokenEndpoint(): ?string
    {
        return $this->tokenEndpoint;
    }

    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }
}
