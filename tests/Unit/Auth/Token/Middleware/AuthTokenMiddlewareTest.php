<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Tests\Unit\Auth\Token\Middleware;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Contracts\RequestAuthenticatorInterface;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Exception\TokenExchangeException;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Token\Middleware\AuthTokenMiddleware;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * @internal
 *
 * @covers \AffordableMobiles\GServerlessSupportLaravel\Auth\Token\Middleware\AuthTokenMiddleware
 */
final class AuthTokenMiddlewareTest extends TestCase
{
    public function testSynchronizesContentLengthWhenBodyMutated(): void
    {
        $middleware      = new AuthTokenMiddleware();
        $capturedRequest = null;

        $handler = static function (RequestInterface $request, array $options) use (&$capturedRequest) {
            $capturedRequest = $request;

            return new FulfilledPromise(new Response(200));
        };

        $invocable = $middleware($handler);

        $initialBody   = 'initial=data';
        $initialLength = (string) \strlen($initialBody);

        $request = new Request(
            'POST',
            'https://api.example.com/endpoint',
            [
                'Content-Type'   => 'application/x-www-form-urlencoded',
                'Content-Length' => $initialLength,
            ],
            $initialBody
        );

        $mutatingAuthenticator = new class implements RequestAuthenticatorInterface {
            public function authenticate(RequestInterface $request, array &$options = []): RequestInterface
            {
                $newBody = ((string) $request->getBody()).'&injected=parameter';

                return $request->withBody(Utils::streamFor($newBody));
            }
        };

        $options = [
            'outbound_auth' => $mutatingAuthenticator,
        ];

        $invocable($request, $options)->wait();

        self::assertNotNull($capturedRequest);
        $expectedBody   = 'initial=data&injected=parameter';
        $expectedLength = (string) \strlen($expectedBody);

        self::assertSame($expectedBody, (string) $capturedRequest->getBody());
        self::assertSame($expectedLength, $capturedRequest->getHeaderLine('Content-Length'));
    }

    public function testRewindsSeekableStreamIfAuthenticatorReadsBody(): void
    {
        $middleware      = new AuthTokenMiddleware();
        $capturedRequest = null;

        $handler = static function (RequestInterface $request, array $options) use (&$capturedRequest) {
            $capturedRequest = $request;

            return new FulfilledPromise(new Response(200));
        };

        $invocable = $middleware($handler);

        $request = new Request('POST', 'https://api.example.com/data', [], 'some payload content');

        $readingAuthenticator = new class implements RequestAuthenticatorInterface {
            public function authenticate(RequestInterface $request, array &$options = []): RequestInterface
            {
                // Read stream without rewinding
                $stream = $request->getBody();
                $stream->getContents();

                return $request->withHeader('X-Checked', '1');
            }
        };

        $options = [
            'outbound_auth' => $readingAuthenticator,
        ];

        $invocable($request, $options)->wait();

        self::assertNotNull($capturedRequest);
        self::assertSame(0, $capturedRequest->getBody()->tell());
        self::assertSame('some payload content', $capturedRequest->getBody()->getContents());
    }

    public function testMiddlewareIsIdempotentAndDoesNotExecuteTwice(): void
    {
        $middleware         = new AuthTokenMiddleware();
        $authExecutionCount = 0;

        $countingAuthenticator = new class($authExecutionCount) implements RequestAuthenticatorInterface {
            public function __construct(private int &$count) {}

            public function authenticate(RequestInterface $request, array &$options = []): RequestInterface
            {
                ++$this->count;

                return $request->withHeader('X-Count', (string) $this->count);
            }
        };

        $innerHandler = static fn (RequestInterface $request, array $options) => new FulfilledPromise(new Response(200));

        // Simulate nested invocation (e.g. Http::globalMiddleware + INJECT_GUZZLE_MIDDLEWARE)
        $inner = $middleware($innerHandler);
        $outer = $middleware($inner);

        $request = new Request('GET', 'https://api.example.com/test');
        $options = ['outbound_auth' => $countingAuthenticator];

        $outer($request, $options)->wait();

        self::assertSame(1, $authExecutionCount);
    }

    public function testReportsOutboundAuthenticationExceptionWhenTelemetryEnabled(): void
    {
        $app = new Container();
        Container::setInstance($app);
        Facade::setFacadeApplication($app);

        $app->singleton('config', static fn () => new Repository([
            'gserverlesssupport' => [
                'http-client' => [
                    'telemetry' => ['enabled' => true],
                ],
            ],
        ]));

        $reported = [];
        $app->singleton(ExceptionHandler::class, function () use (&$reported) {
            return new class($reported) implements ExceptionHandler {
                public function __construct(private array &$reported) {}

                public function report(\Throwable $e): void
                {
                    $this->reported[] = $e;
                }

                public function shouldReport(\Throwable $e): bool
                {
                    return true;
                }

                public function render($request, \Throwable $e)
                {
                    return new Response(500);
                }

                public function renderForConsole($output, \Throwable $e): void {}
            };
        });

        $middleware = new AuthTokenMiddleware();

        $failingAuthenticator = new class implements RequestAuthenticatorInterface {
            public function authenticate(RequestInterface $request, array &$options = []): RequestInterface
            {
                throw new TokenExchangeException('Token endpoint failed');
            }
        };

        $handler   = static fn (RequestInterface $request, array $options) => new FulfilledPromise(new Response(200));
        $invocable = $middleware($handler);

        $request = new Request('GET', 'https://api.example.com/test');
        $options = ['outbound_auth' => $failingAuthenticator];

        try {
            $invocable($request, $options);
            self::fail('Expected TokenExchangeException was not thrown');
        } catch (TokenExchangeException $e) {
            self::assertSame('Token endpoint failed', $e->getMessage());
        }

        self::assertCount(1, $reported);
        self::assertInstanceOf(TokenExchangeException::class, $reported[0]);
        self::assertSame('Token endpoint failed', $reported[0]->getMessage());
    }
}
