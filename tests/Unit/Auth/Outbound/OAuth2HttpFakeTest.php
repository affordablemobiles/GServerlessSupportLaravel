<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Tests\Unit\Auth\Outbound;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Exception\InvalidClientCredentialsException;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Exception\TokenExchangeException;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound\OAuth2ClientCredentialsAuthenticator;
use GuzzleHttp\Psr7\Request;
use Illuminate\Cache\CacheManager;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound\AbstractOAuth2Authenticator
 */
final class OAuth2HttpFakeTest extends TestCase
{
    private Container $app;
    private Factory $httpFactory;

    protected function setUp(): void
    {
        parent::setUp();

        Facade::clearResolvedInstances();

        $this->app = new Container();
        Container::setInstance($this->app);
        Facade::setFacadeApplication($this->app);

        $this->httpFactory = new Factory();
        $this->app->singleton('http.client.factory', fn () => $this->httpFactory);
        $this->app->alias('http.client.factory', Factory::class);

        $this->app->singleton('cache', fn () => new CacheManager($this->app));
        $this->app->singleton('config', static fn () => new Repository([
            'cache' => [
                'default' => 'array',
                'stores'  => ['array' => ['driver' => 'array']],
            ],
            'gserverlesssupport' => [
                'http-client' => [
                    'telemetry' => ['enabled' => false],
                ],
            ],
        ]));
    }

    public function testHttpFakeInterceptsTokenRequestAutomatically(): void
    {
        Http::fake([
            'https://auth.example.com/oauth/token' => Http::response([
                'access_token' => 'faked_token_abc_123',
                'expires_in'   => 3600,
            ], 200),
        ]);

        $authenticator = new OAuth2ClientCredentialsAuthenticator(
            tokenUrl: 'https://auth.example.com/oauth/token',
            clientId: 'client_id_val',
            clientSecret: 'secret_val',
        );

        $request       = new Request('GET', 'https://api.example.com/orders');
        $authenticated = $authenticator->authenticate($request);

        self::assertSame('Bearer faked_token_abc_123', $authenticated->getHeaderLine('Authorization'));
    }

    public function testHttpFake401ThrowsInvalidClientCredentialsException(): void
    {
        Http::fake([
            'https://auth.example.com/oauth/token' => Http::response([
                'error'             => 'invalid_client',
                'error_description' => 'Client authentication failed',
            ], 401),
        ]);

        $authenticator = new OAuth2ClientCredentialsAuthenticator(
            tokenUrl: 'https://auth.example.com/oauth/token',
            clientId: 'bad_client',
            clientSecret: 'bad_secret',
        );

        $this->expectException(InvalidClientCredentialsException::class);
        $this->expectExceptionMessage('Client authentication failed');

        $request = new Request('GET', 'https://api.example.com/orders');
        $authenticator->authenticate($request);
    }

    public function testHttpFake500ThrowsTokenExchangeException(): void
    {
        Http::fake([
            'https://auth.example.com/oauth/token' => Http::response([
                'error' => 'server_error',
            ], 500),
        ]);

        $authenticator = new OAuth2ClientCredentialsAuthenticator(
            tokenUrl: 'https://auth.example.com/oauth/token',
            clientId: 'client_val',
            clientSecret: 'secret_val',
        );

        $this->expectException(TokenExchangeException::class);
        $this->expectExceptionMessage('[auth.example.com] POST -> 500');

        $request = new Request('GET', 'https://api.example.com/orders');
        $authenticator->authenticate($request);
    }
}
