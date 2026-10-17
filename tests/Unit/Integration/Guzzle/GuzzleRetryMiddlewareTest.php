<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Tests\Unit\Integration\Guzzle;

use AffordableMobiles\GServerlessSupportLaravel\Integration\Guzzle\GuzzleRetryMiddleware;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * @internal
 *
 * @covers \AffordableMobiles\GServerlessSupportLaravel\Integration\Guzzle\GuzzleRetryMiddleware
 */
final class GuzzleRetryMiddlewareTest extends TestCase
{
    public function testRetryMiddlewareIsIdempotentWhenNested(): void
    {
        $callCount = 0;

        $baseHandler = static function (RequestInterface $request, array $options) use (&$callCount) {
            ++$callCount;

            return new FulfilledPromise(new Response(200));
        };

        // Simulate nested invocation (e.g. Http::globalMiddleware + INJECT_GUZZLE_MIDDLEWARE)
        $factory = GuzzleRetryMiddleware::factory();
        $inner   = $factory($baseHandler);
        $outer   = $factory($inner);

        $request = new Request('GET', 'https://api.example.com/status');
        $options = [];

        $outer($request, $options)->wait();

        self::assertSame(1, $callCount);
    }
}
