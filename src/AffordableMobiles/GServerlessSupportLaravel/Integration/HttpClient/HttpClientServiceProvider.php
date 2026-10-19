<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Integration\HttpClient;

use AffordableMobiles\GServerlessSupportLaravel\Auth\OutboundAuth;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Token\Middleware\AuthTokenMiddleware;
use AffordableMobiles\GServerlessSupportLaravel\Integration\Guzzle\GuzzleRetryMiddleware;
use AffordableMobiles\GServerlessSupportLaravel\Integration\HttpClient\Listeners\OutboundHttpTelemetryListener;
use AffordableMobiles\GServerlessSupportLaravel\Integration\Soap\SoapClient;
use AffordableMobiles\GServerlessSupportLaravel\Support\CertificateMaterializer;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

class HttpClientServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../../../config/gserverlesssupport.php',
            'gserverlesssupport'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(Dispatcher $events): void
    {
        $config = $this->app->make('config');

        // 1. Global default request options (timeouts, verify, CURLOPT_RESOLVE)
        $globalOptions = (array) $config->get('gserverlesssupport.http-client.options', []);
        if (!empty($globalOptions)) {
            if (!empty($globalOptions['resolve'])) {
                $curlResolve = \defined('CURLOPT_RESOLVE') ? CURLOPT_RESOLVE : 10203;
                $globalOptions['curl'] ??= [];
                $globalOptions['curl'][$curlResolve] = array_values(array_unique(array_merge(
                    (array) ($globalOptions['curl'][$curlResolve] ?? []),
                    (array) $globalOptions['resolve']
                )));
                unset($globalOptions['resolve']);
            }
            Http::globalOptions($globalOptions);
        }

        // 2. Global retry middleware for GCP serverless network / connect timeouts
        if ($config->get('gserverlesssupport.http-client.retry.enabled', true)) {
            Http::globalMiddleware(GuzzleRetryMiddleware::factory());
        }

        // 3. Global Auth token injection for outbound microservice and 3rd party calls
        if ($config->get('gserverlesssupport.http-client.auth.enabled', true)) {
            Http::globalMiddleware(static fn (callable $handler): callable => AuthTokenMiddleware::factory()($handler));
        }

        // 4. Automated Error Reporting telemetry for outbound HTTP calls
        if ($config->get('gserverlesssupport.http-client.telemetry.enabled', true)) {
            $events->subscribe(OutboundHttpTelemetryListener::class);
        }

        // 5. Register withOutboundAuth macro on PendingRequest and Http facade
        PendingRequest::macro('withOutboundAuth', function (mixed $auth) {
            // @var PendingRequest $this
            return $this->withOptions(['outbound_auth' => $auth]);
        });

        // 6. Register withClientCert macro for mTLS on PendingRequest and Http facade
        PendingRequest::macro('withClientCert', function (array|string $cert, null|array|string $key = null, ?string $certPassword = null, ?string $keyPassword = null, bool $inMemory = true) {
            /** @var PendingRequest $this */
            $options = [];

            CertificateMaterializer::bindToRequestOptions(
                $options,
                $cert,
                $key,
                $certPassword,
                $keyPassword,
                $inMemory,
            );

            return $this->withOptions($options);
        });

        // 7. Register Http::soap() and PendingRequest::soap() macros for native Http-backed SoapClient
        PendingRequest::macro('soap', function (?string $wsdl = null, array $options = []): SoapClient {
            // @var PendingRequest $this
            return new SoapClient($wsdl, $options, $this);
        });

        Http::macro('soap', function (?string $wsdl = null, array $options = []): SoapClient {
            // @var \Illuminate\Http\Client\Factory $this
            return $this->createPendingRequest()->soap($wsdl, $options);
        });

        // 8. Register Http::ping() macro for simple egress / healthcheck probes
        Http::macro('ping', function (string $url, int $timeout = 2): bool {
            // @var \Illuminate\Http\Client\Factory $this
            try {
                return $this->withoutRedirecting()
                    ->timeout($timeout)
                    ->connectTimeout($timeout)
                    ->get($url)
                    ->successful()
                ;
            } catch (\Throwable) {
                return false;
            }
        });

        // 9. Register Response::xml() macro for XML payload parsing
        Response::macro('xml', function (int $options = LIBXML_NOCDATA): false|\SimpleXMLElement {
            /** @var Response $this */
            $body = $this->body();
            if ('' === trim($body)) {
                return false;
            }

            try {
                return simplexml_load_string($body, 'SimpleXMLElement', $options);
            } catch (\Throwable) {
                return false;
            }
        });

        // 10. Reset request-scoped runtime auth registry at the end of request lifecycle
        if (method_exists($this->app, 'terminating')) {
            $this->app->terminating(static function (): void {
                OutboundAuth::clearRequestMap();
            });
        }
    }
}
