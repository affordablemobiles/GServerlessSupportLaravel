<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Tests\Unit\Auth\Outbound;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound\ApiKeyAuthenticator;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound\ApiKeyAuthenticator
 */
final class ApiKeyAuthenticatorTest extends TestCase
{
    public function testHeaderInjection(): void
    {
        $authenticator = new ApiKeyAuthenticator(
            key: 'secret-token',
            name: 'X-API-Key',
            in: 'header'
        );

        $request       = new Request('GET', 'https://api.example.com/data');
        $authenticated = $authenticator->authenticate($request);

        self::assertSame('secret-token', $authenticated->getHeaderLine('X-API-Key'));
    }

    public function testHeaderInjectionWithPrefix(): void
    {
        $authenticator = new ApiKeyAuthenticator(
            key: 'secret-token',
            name: 'Authorization',
            in: 'header',
            prefix: 'Token'
        );

        $request       = new Request('GET', 'https://api.example.com/data');
        $authenticated = $authenticator->authenticate($request);

        self::assertSame('Token secret-token', $authenticated->getHeaderLine('Authorization'));
    }

    public function testQueryParameterInjection(): void
    {
        $authenticator = new ApiKeyAuthenticator(
            key: 'my key & more',
            name: 'api_key',
            in: 'query'
        );

        $request       = new Request('GET', 'https://api.example.com/data?existing=1');
        $authenticated = $authenticator->authenticate($request);

        self::assertSame('existing=1&api_key=my%20key%20%26%20more', $authenticated->getUri()->getQuery());
    }

    public function testFormParamsInjectionUpdatesContentLength(): void
    {
        $authenticator = new ApiKeyAuthenticator(
            key: 'secret123',
            name: 'api_key',
            in: 'form_params'
        );

        $originalBody   = 'grant_type=client_credentials&client_id=my-app';
        $originalLength = (string) \strlen($originalBody);

        // Simulate request after Guzzle PrepareBodyMiddleware set initial Content-Length
        $request = new Request(
            'POST',
            'https://auth.example.com/token',
            [
                'Content-Type'   => 'application/x-www-form-urlencoded',
                'Content-Length' => $originalLength,
            ],
            $originalBody
        );

        $authenticated = $authenticator->authenticate($request);

        $expectedBody   = 'grant_type=client_credentials&client_id=my-app&api_key=secret123';
        $expectedLength = (string) \strlen($expectedBody);

        self::assertSame($expectedBody, (string) $authenticated->getBody());
        self::assertSame($expectedLength, $authenticated->getHeaderLine('Content-Length'));
        self::assertNotSame($originalLength, $authenticated->getHeaderLine('Content-Length'));
    }
}
