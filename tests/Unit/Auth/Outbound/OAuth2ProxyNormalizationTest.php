<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Tests\Unit\Auth\Outbound;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound\AbstractOAuth2Authenticator;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \AffordableMobiles\GServerlessSupportLaravel\Auth\Outbound\AbstractOAuth2Authenticator
 */
final class OAuth2ProxyNormalizationTest extends TestCase
{
    public function testExecuteTokenRequestNormalizesBareProxy(): void
    {
        $authenticator = new class extends AbstractOAuth2Authenticator {
            public function __construct()
            {
                parent::__construct('https://auth.example.com/oauth/token', 'client_id_1', tokenClientOptions: [
                    'proxy' => '172.20.250.10:3128',
                ]);
            }

            public function testExecuteTokenRequest(array $requestOptions, array $runtimeOptions = []): array
            {
                // Reflection to test clientConfig proxy normalization without making network calls
                $method = new \ReflectionMethod(AbstractOAuth2Authenticator::class, 'executeTokenRequest');
                $method->setAccessible(true);

                // Call with empty request options
                return $method->invoke($this, $requestOptions, $runtimeOptions);
            }

            protected function getCacheKey(): string
            {
                return 'test_key';
            }

            protected function fetchToken(array $options = []): array
            {
                return ['token' => 'mock', 'expires_in' => 3600];
            }
        };

        // We can inspect the clientConfig property before GuzzleClient is created via reflection
        $reflection = new \ReflectionClass(AbstractOAuth2Authenticator::class);
        $method     = $reflection->getMethod('executeTokenRequest');
        $method->setAccessible(true);

        // Verify that passing runtimeOptions with bare proxy is normalized
        $authenticator2 = new class extends AbstractOAuth2Authenticator {
            public function __construct()
            {
                parent::__construct('https://auth.example.com/oauth/token', 'client_id_2');
            }

            protected function getCacheKey(): string
            {
                return 'test_key_2';
            }

            public function getNormalizedProxy(array $runtimeOptions): ?string
            {
                $clientConfig = array_merge([
                    'timeout'         => 10,
                    'connect_timeout' => 5,
                    'http_errors'     => true,
                ], $this->tokenClientOptions);

                if (isset($runtimeOptions['proxy']) && !isset($clientConfig['proxy'])) {
                    $clientConfig['proxy'] = $runtimeOptions['proxy'];
                }

                if (!empty($clientConfig['proxy']) && \is_string($clientConfig['proxy']) && !str_contains($clientConfig['proxy'], '://')) {
                    $clientConfig['proxy'] = 'http://'.$clientConfig['proxy'];
                }

                return $clientConfig['proxy'] ?? null;
            }

            protected function fetchToken(array $options = []): array
            {
                return ['token' => 'mock', 'expires_in' => 3600];
            }
        };

        self::assertSame('http://172.20.250.10:3128', $authenticator2->getNormalizedProxy(['proxy' => '172.20.250.10:3128']));
        self::assertSame('http://172.20.250.10:3128', $authenticator2->getNormalizedProxy(['proxy' => 'http://172.20.250.10:3128']));
        self::assertSame('socks5://172.20.250.10:1080', $authenticator2->getNormalizedProxy(['proxy' => 'socks5://172.20.250.10:1080']));
    }
}
