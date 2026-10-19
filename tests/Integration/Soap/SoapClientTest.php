<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Tests\Integration\Soap;

use AffordableMobiles\GServerlessSupportLaravel\Integration\HttpClient\HttpClientServiceProvider;
use AffordableMobiles\GServerlessSupportLaravel\Integration\HttpClient\Listeners\OutboundHttpTelemetryListener;
use AffordableMobiles\GServerlessSupportLaravel\Integration\Soap\Exceptions\SoapTransportException;
use AffordableMobiles\GServerlessSupportLaravel\Integration\Soap\SoapClient;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class SoapClientTest extends TestCase
{
    protected Container $app;
    protected Factory $httpFactory;
    protected Dispatcher $events;

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

        $this->events = new Dispatcher($this->app);
        $this->app->singleton('events', fn () => $this->events);

        $this->app->singleton('config', static fn () => new Repository([
            'gserverlesssupport' => [
                'http-client' => [
                    'retry'     => ['enabled' => false],
                    'auth'      => ['enabled' => false],
                    'telemetry' => ['enabled' => false],
                ],
            ],
        ]));

        $provider = new HttpClientServiceProvider($this->app);
        $provider->boot($this->events);
    }

    public function testSoap11HeadersAndDoRequest(): void
    {
        $this->httpFactory->fake([
            'http://ws.example.com/soap' => $this->httpFactory->response('<soap:Envelope><soap:Body><result>OK</result></soap:Body></soap:Envelope>', 200, ['Content-Type' => 'text/xml']),
        ]);

        $client = Http::soap(null, [
            'location'     => 'http://ws.example.com/soap',
            'uri'          => 'urn:test',
            'soap_version' => SOAP_1_1,
        ]);

        $xmlResponse = $client->__doRequest('<soap:Envelope/>', 'http://ws.example.com/soap', 'urn:test#DoAction', SOAP_1_1, 0);

        self::assertStringContainsString('<result>OK</result>', (string) $xmlResponse);

        $reqHeaders = (string) $client->__getLastRequestHeaders();
        self::assertStringContainsString('Content-Type: text/xml; charset=utf-8', $reqHeaders);
        self::assertStringContainsString('SOAPAction: "urn:test#DoAction"', $reqHeaders);
    }

    public function testSoap12HeadersAndDoRequest(): void
    {
        $this->httpFactory->fake([
            'http://ws.example.com/soap12' => $this->httpFactory->response('<soap:Envelope><soap:Body><result>OK12</result></soap:Body></soap:Envelope>', 200, ['Content-Type' => 'application/soap+xml']),
        ]);

        $client = Http::soap(null, [
            'location'     => 'http://ws.example.com/soap12',
            'uri'          => 'urn:test',
            'soap_version' => SOAP_1_2,
        ]);

        $xmlResponse = $client->__doRequest('<soap:Envelope/>', 'http://ws.example.com/soap12', 'urn:test#DoAction12', SOAP_1_2, false);

        self::assertStringContainsString('<result>OK12</result>', (string) $xmlResponse);

        $reqHeaders = (string) $client->__getLastRequestHeaders();
        self::assertStringContainsString('Content-Type: application/soap+xml; charset=utf-8; action="urn:test#DoAction12"', $reqHeaders);
        self::assertStringNotContainsString('SOAPAction:', $reqHeaders);
    }

    public function testSoapFaultReturnsBodyForExtSoapParsing(): void
    {
        $faultXml = '<soap:Envelope><soap:Body><soap:Fault><faultcode>soap:Client</faultcode><faultstring>Invalid PAC</faultstring></soap:Fault></soap:Body></soap:Envelope>';

        $this->httpFactory->fake([
            'http://ws.example.com/fault' => $this->httpFactory->response($faultXml, 500, ['Content-Type' => 'text/xml']),
        ]);

        $client = Http::soap(null, [
            'location'     => 'http://ws.example.com/fault',
            'uri'          => 'urn:test',
            'soap_version' => SOAP_1_1,
        ]);

        $body = $client->__doRequest('<soap:Envelope/>', 'http://ws.example.com/fault', 'urn:test#Fault', SOAP_1_1, 0);

        self::assertSame($faultXml, $body);
    }

    public function testNonSoap500ThrowsSoapTransportException(): void
    {
        $html502 = '<html><head><title>502 Bad Gateway</title></head><body>Crash</body></html>';

        $this->httpFactory->fake([
            'http://ws.example.com/crash' => $this->httpFactory->response($html502, 502, ['Content-Type' => 'text/html']),
        ]);

        $client = Http::soap(null, [
            'location'     => 'http://ws.example.com/crash',
            'uri'          => 'urn:test',
            'soap_version' => SOAP_1_1,
        ]);

        $this->expectException(SoapTransportException::class);
        $this->expectExceptionMessage('[ws.example.com] SOAP request failed with HTTP 502');

        try {
            $client->__doRequest('<soap:Envelope/>', 'http://ws.example.com/crash', 'urn:test#Crash', SOAP_1_1, 0);
        } catch (SoapTransportException $e) {
            self::assertInstanceOf(RequestException::class, $e->getPrevious());

            throw $e;
        }
    }

    public function testPendingRequestSoapMacroChaining(): void
    {
        $this->httpFactory->fake([
            'http://ws.example.com/chained' => $this->httpFactory->response('<soap:Envelope><soap:Body><status>OK</status></soap:Body></soap:Envelope>', 200, ['Content-Type' => 'text/xml']),
        ]);

        $client = Http::withHeaders(['X-Custom-Trace' => 'trace-123'])
            ->timeout(25)
            ->soap(null, [
                'location' => 'http://ws.example.com/chained',
                'uri'      => 'urn:test',
            ])
        ;

        $client->__doRequest('<soap:Envelope/>', 'http://ws.example.com/chained', 'urn:test#Action', SOAP_1_1, 0);

        $headers = (string) $client->__getLastRequestHeaders();
        self::assertStringContainsString('X-Custom-Trace: trace-123', $headers);
    }

    public function testTelemetryListenerFiltersSoapClientFaults(): void
    {
        $listener = new class($this->app->make('config')) extends OutboundHttpTelemetryListener {
            public function testIsSoapClientFault(Response $response): bool
            {
                return $this->isSoapClientFault($response);
            }
        };

        // 1. Standard SOAP 1.1 Client Fault
        $resp1 = new Response(new PsrResponse(500, [], '<soap:Fault><faultcode>soap:Client</faultcode></soap:Fault>'));
        self::assertTrue($listener->testIsSoapClientFault($resp1));

        // 2. Namespaced SOAP 1.1 Faultcode
        $resp2 = new Response(new PsrResponse(500, [], '<soapenv:Fault><soapenv:faultcode>soapenv:Client.Authentication</soapenv:faultcode></soapenv:Fault>'));
        self::assertTrue($listener->testIsSoapClientFault($resp2));

        // 3. WSSE FailedAuthentication
        $resp3 = new Response(new PsrResponse(500, [], '<Fault><faultcode>wsse:FailedAuthentication</faultcode></Fault>'));
        self::assertTrue($listener->testIsSoapClientFault($resp3));

        // 4. SOAP 1.2 Namespaced Value
        $resp4 = new Response(new PsrResponse(500, [], '<soap:Fault><soap:Code><soap:Value>soap:Sender</soap:Value></soap:Code></soap:Fault>'));
        self::assertTrue($listener->testIsSoapClientFault($resp4));

        // 5. Server Fault should NOT be filtered (must report to SRE)
        $resp5 = new Response(new PsrResponse(500, [], '<soap:Fault><faultcode>soap:Server</faultcode></soap:Fault>'));
        self::assertFalse($listener->testIsSoapClientFault($resp5));

        // 6. Non-SOAP HTML crash should NOT be filtered
        $resp6 = new Response(new PsrResponse(500, [], '<html>Internal Crash</html>'));
        self::assertFalse($listener->testIsSoapClientFault($resp6));
    }

    public function testRemoteWsdlRelativeSchemaLocationRewriting(): void
    {
        $wsdlWithRelativeImports = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<definitions xmlns="http://schemas.xmlsoap.org/wsdl/" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
    <types>
        <xsd:schema>
            <xsd:import schemaLocation="types/subtypes.xsd" namespace="http://example.com/types"/>
            <xsd:include schemaLocation="../common/common.xsd"/>
            <xsd:import schemaLocation="https://other.com/external.xsd" namespace="http://other.com"/>
        </xsd:schema>
    </types>
</definitions>
XML;

        $this->httpFactory->fake([
            'https://ws.example.com/services/v1/service.wsdl?wsdl' => $this->httpFactory->response($wsdlWithRelativeImports, 200),
        ]);

        $client = new class('https://ws.example.com/services/v1/service.wsdl?wsdl', ['cache_wsdl' => 0]) extends SoapClient {
            public function __construct(string $wsdl, array $options)
            {
                $this->extractCustomOptions($options);
                $this->resolveRemoteWsdl($wsdl, $options);
            }

            public function getResolvedWsdl(string $wsdl, array $options): string
            {
                $cachedFile = $this->resolveRemoteWsdl($wsdl, $options);

                return (string) file_get_contents($cachedFile);
            }
        };

        $rewrittenXml = $client->getResolvedWsdl('https://ws.example.com/services/v1/service.wsdl?wsdl', []);

        self::assertStringContainsString('schemaLocation="https://ws.example.com/services/v1/types/subtypes.xsd"', $rewrittenXml);
        self::assertStringContainsString('schemaLocation="https://ws.example.com/services/common/common.xsd"', $rewrittenXml);
        self::assertStringContainsString('schemaLocation="https://other.com/external.xsd"', $rewrittenXml);
    }
}
