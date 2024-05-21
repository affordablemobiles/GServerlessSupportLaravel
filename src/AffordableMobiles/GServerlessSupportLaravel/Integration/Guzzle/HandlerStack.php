<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Integration\Guzzle;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Token\Middleware\AuthTokenMiddleware;
use GuzzleHttp\HandlerStack as BaseHandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Utils;
use Psr\Http\Message\RequestInterface;

class HandlerStack extends BaseHandlerStack
{
    /**
     * Creates a default handler stack that can be used by clients.
     *
     * The returned handler will wrap the provided handler or use the most
     * appropriate default handler for your system. The returned HandlerStack has
     * support for cookies, redirects, HTTP error exceptions, and preparing a body
     * before sending.
     *
     * The returned handler stack can be passed to a client in the "handler"
     * option.
     *
     * @param null|(callable(RequestInterface, array): PromiseInterface) $handler HTTP handler function to use with the stack. If no
     *                                                                            handler is provided, the best handler for your
     *                                                                            system will be utilized.
     */
    public static function create(?callable $handler = null): self
    {
        $stack = new self($handler ?: Utils::chooseHandler());

        $stack->push(Middleware::httpErrors(), 'http_errors');
        $stack->push(Middleware::redirect(), 'allow_redirects');
        $stack->push(Middleware::cookies(), 'cookies');
        $stack->push(Middleware::prepareBody(), 'prepare_body');
        $stack->push(GuzzleRetryMiddleware::factory(), 'retry');
        $stack->push(AuthTokenMiddleware::factory(), 'auth_token');

        return $stack;
    }
}
