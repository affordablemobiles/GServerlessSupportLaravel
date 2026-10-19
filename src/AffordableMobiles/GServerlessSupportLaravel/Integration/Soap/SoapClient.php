<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Integration\Soap;

use AffordableMobiles\GServerlessSupportLaravel\Auth\Contracts\RequestAuthenticatorInterface;
use AffordableMobiles\GServerlessSupportLaravel\Auth\OutboundAuth;
use AffordableMobiles\GServerlessSupportLaravel\Integration\Soap\Exceptions\SoapTransportException;
use AffordableMobiles\GServerlessSupportLaravel\Support\CertificateMaterializer;
use GuzzleHttp\Psr7\Message;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SoapClient extends \SoapClient
{
    protected ?string $lastRequestHeaders  = null;
    protected ?string $lastResponseHeaders = null;
    protected ?string $lastRequestBody     = null;
    protected ?string $lastResponseBody    = null;

    /**
     * @var array<string,string>
     */
    protected array $customHeaders = [];

    /**
     * @var array<string,mixed>
     */
    protected array $httpOptions = [];

    protected ?RequestAuthenticatorInterface $outboundAuth = null;

    /**
     * @var null|array<string,mixed>
     */
    protected ?array $clientCert = null;

    protected null|float|int $timeout        = null;
    protected null|float|int $connectTimeout = null;

    protected ?PendingRequest $pendingRequest = null;

    /**
     * @param null|string         $wsdl           WSDL file path, remote URL, or null for WSDL-less mode
     * @param array<string,mixed> $options        SoapClient options (can include 'outbound_auth', 'client_cert', 'http_options', etc.)
     * @param null|PendingRequest $pendingRequest Optional pre-configured Laravel HTTP PendingRequest
     */
    public function __construct(?string $wsdl, array $options = [], ?PendingRequest $pendingRequest = null)
    {
        $this->pendingRequest = $pendingRequest ?? ($options['pending_request'] ?? null);
        unset($options['pending_request']);

        // Extract custom configuration options before parent constructor
        $this->extractCustomOptions($options);

        $options['trace']      ??= true;
        $options['exceptions'] ??= true;

        // Configure serverless WSDL caching in tmpfs by default
        $this->configureWsdlCache($options);

        // Intercept remote WSDL URLs and fetch via HTTP client (inheriting auth, mTLS, timeouts, and options)
        if (null !== $wsdl && (str_starts_with($wsdl, 'http://') || str_starts_with($wsdl, 'https://'))) {
            $wsdl = $this->resolveRemoteWsdl($wsdl, $options);
        }

        parent::__construct($wsdl, $options);
    }

    /**
     * Intercept and execute the SOAP HTTP request via Laravel's Http client.
     *
     * @param string   $request  SOAP XML request envelope
     * @param string   $location Destination endpoint URL
     * @param string   $action   SOAPAction URI
     * @param int      $version  SOAP version (SOAP_1_1 or SOAP_1_2)
     * @param bool|int $oneWay   Whether to ignore the response (one-way operation)
     */
    public function __doRequest(
        string $request,
        string $location,
        string $action,
        int $version,
        bool|int $oneWay = false,
    ): ?string {
        $contentType = $this->resolveContentType($action, $version);
        $headers     = $this->resolveHeaders($action, $version, $contentType);

        $pendingRequest = $this->buildPendingRequest()
            ->withHeaders($headers)
            ->withBody($request, $contentType)
        ;

        $this->lastRequestBody = $request;

        try {
            $response = $pendingRequest->send('POST', $location);
        } catch (ConnectionException $e) {
            $host = parse_url($location, PHP_URL_HOST) ?? 'unknown-host';

            throw new SoapTransportException(
                \sprintf('Could not connect to SOAP host [%s]: %s', $host, $e->getMessage()),
                'HTTP',
                $e
            );
        } catch (\Throwable $e) {
            $host = parse_url($location, PHP_URL_HOST) ?? 'unknown-host';

            throw new SoapTransportException(
                \sprintf('SOAP request to [%s] failed: %s', $host, $e->getMessage()),
                'HTTP',
                $e
            );
        }

        // Record wire headers for __getLastRequestHeaders() and __getLastResponseHeaders()
        $this->recordWireMetadata($response, $location);

        if ((bool) $oneWay) {
            return '';
        }

        $status = $response->status();
        $body   = $response->body();

        // 2xx Success: return XML payload to ext-soap engine
        if ($response->successful()) {
            return $body;
        }

        // SOAP Fault Envelope on HTTP 500 (or 400 in SOAP 1.2):
        // Return body so ext-soap parses the fault and throws a typed \SoapFault
        if ($this->isSoapFaultXml($body)) {
            return $body;
        }

        // Non-SOAP error response (e.g. HTML 502/503/504 gateway crash, 401/403 auth rejection)
        $host    = parse_url($location, PHP_URL_HOST)                    ?? 'unknown-host';
        $summary = Message::bodySummary($response->toPsrResponse(), 250) ?? 'No response body';

        throw new SoapTransportException(
            \sprintf('[%s] SOAP request failed with HTTP %d %s: %s', $host, $status, $response->reason(), $summary),
            'HTTP',
            $response->toException()
        );
    }

    public function __getLastRequest(): ?string
    {
        return $this->lastRequestBody ?? parent::__getLastRequest();
    }

    public function __getLastResponse(): ?string
    {
        return $this->lastResponseBody ?? parent::__getLastResponse();
    }

    public function __getLastRequestHeaders(): ?string
    {
        return $this->lastRequestHeaders;
    }

    public function __getLastResponseHeaders(): ?string
    {
        return $this->lastResponseHeaders;
    }

    /**
     * Add custom HTTP headers to outgoing SOAP requests.
     *
     * @param array<string,string> $headers
     */
    public function withHeaders(array $headers): self
    {
        $this->customHeaders = array_merge($this->customHeaders, $headers);

        return $this;
    }

    /**
     * Set a single custom HTTP header.
     */
    public function withHeader(string $name, string $value): self
    {
        $this->customHeaders[$name] = $value;

        return $this;
    }

    /**
     * Set outbound authentication for the SOAP client.
     */
    public function withOutboundAuth(mixed $auth): self
    {
        $this->outboundAuth = OutboundAuth::fromConfig($auth);

        return $this;
    }

    /**
     * Set mutual TLS client certificate.
     */
    public function withClientCert(
        array|string $cert,
        null|array|string $key = null,
        ?string $certPassword = null,
        ?string $keyPassword = null,
        bool $inMemory = true,
    ): self {
        $this->clientCert = [
            'cert'         => $cert,
            'key'          => $key,
            'certPassword' => $certPassword,
            'keyPassword'  => $keyPassword,
            'inMemory'     => $inMemory,
        ];

        return $this;
    }

    /**
     * Set underlying Guzzle HTTP options (proxy, verify, etc.).
     *
     * @param array<string,mixed> $options
     */
    public function withHttpOptions(array $options): self
    {
        $this->httpOptions = array_merge($this->httpOptions, $options);

        return $this;
    }

    /**
     * Set request timeout in seconds.
     */
    public function withTimeout(float|int $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Set connection timeout in seconds.
     */
    public function withConnectTimeout(float|int $seconds): self
    {
        $this->connectTimeout = $seconds;

        return $this;
    }

    /**
     * Build the Laravel HTTP PendingRequest applying auth, cert, timeouts, and options.
     */
    protected function buildPendingRequest(): PendingRequest
    {
        $pending = null !== $this->pendingRequest
            ? clone $this->pendingRequest
            : Http::withOptions([
                'version' => 1.1,
            ]);

        if (!empty($this->httpOptions)) {
            $pending->withOptions($this->httpOptions);
        }

        if (null !== $this->timeout) {
            $pending->timeout($this->timeout);
        }

        if (null !== $this->connectTimeout) {
            $pending->connectTimeout($this->connectTimeout);
        }

        if (null !== $this->outboundAuth) {
            $pending->withOutboundAuth($this->outboundAuth);
        }

        if (null !== $this->clientCert) {
            $pending->withClientCert(
                cert: $this->clientCert['cert']                 ?? $this->clientCert[0] ?? '',
                key: $this->clientCert['key']                   ?? $this->clientCert['ssl_key'] ?? $this->clientCert[1] ?? null,
                certPassword: $this->clientCert['certPassword'] ?? $this->clientCert['cert_password'] ?? null,
                keyPassword: $this->clientCert['keyPassword']   ?? $this->clientCert['key_password'] ?? null,
                inMemory: (bool) ($this->clientCert['inMemory'] ?? $this->clientCert['in_memory'] ?? true),
            );
        }

        if (!empty($this->customHeaders)) {
            $pending->withHeaders($this->customHeaders);
        }

        return $pending;
    }

    /**
     * Extract custom options before invoking the parent constructor.
     *
     * @param array<string,mixed> $options
     */
    protected function extractCustomOptions(array &$options): void
    {
        if (isset($options['outbound_auth'])) {
            $this->withOutboundAuth($options['outbound_auth']);
            unset($options['outbound_auth']);
        }

        if (isset($options['client_cert'])) {
            if (\is_array($options['client_cert'])) {
                $this->clientCert = $options['client_cert'];
            }
            unset($options['client_cert']);
        } elseif (isset($options['mtls'])) {
            if (\is_array($options['mtls'])) {
                $this->clientCert = $options['mtls'];
            }
            unset($options['mtls']);
        }

        if (isset($options['http_options'])) {
            $this->withHttpOptions((array) $options['http_options']);
            unset($options['http_options']);
        }

        if (isset($options['headers'])) {
            $this->withHeaders((array) $options['headers']);
            unset($options['headers']);
        }

        if (isset($options['timeout'])) {
            $this->timeout = $options['timeout'];
            unset($options['timeout']);
        }

        if (isset($options['connect_timeout'])) {
            $this->connectTimeout = $options['connect_timeout'];
            unset($options['connect_timeout']);
        }
    }

    /**
     * Resolve the Content-Type header string based on SOAP version.
     */
    protected function resolveContentType(string $action, int $version): string
    {
        if (SOAP_1_2 === $version) {
            return '' !== $action
                ? \sprintf('application/soap+xml; charset=utf-8; action="%s"', trim($action, '"'))
                : 'application/soap+xml; charset=utf-8';
        }

        return 'text/xml; charset=utf-8';
    }

    /**
     * Resolve headers for the SOAP request.
     *
     * @return array<string,string>
     */
    protected function resolveHeaders(string $action, int $version, string $contentType): array
    {
        $headers = [
            'Content-Type'  => $contentType,
            'Expect'        => '', // Disable Expect: 100-continue stalls
            'X-Rpc-System'  => 'soap',
            'X-Soap-Action' => $action,
        ];

        // SOAP 1.1 requires SOAPAction header (double-quoted per RFC 2616 / W3C spec)
        if (SOAP_1_2 !== $version) {
            $headers['SOAPAction'] = '"'.trim($action, '"').'"';
        }

        return array_merge($headers, $this->customHeaders);
    }

    /**
     * Configure WSDL caching directory in tmpfs.
     *
     * @param array<string,mixed> $options
     */
    protected function configureWsdlCache(array &$options): void
    {
        try {
            $dir = (string) config(
                'gserverlesssupport.system_cache_path',
                CertificateMaterializer::DEFAULT_SYSTEM_CACHE_PATH
            ).'/wsdl';

            if (!is_dir($dir)) {
                @mkdir($dir, 0o700, true);
            }

            @ini_set('soap.wsdl_cache_dir', $dir);
            $options['cache_wsdl'] ??= \defined('WSDL_CACHE_BOTH') ? WSDL_CACHE_BOTH : 3;
        } catch (\Throwable) {
            // Ignore cache directory failure
        }
    }

    /**
     * Download and cache remote WSDL via HTTP client (inheriting proxy, auth, and retries).
     *
     * @param array<string,mixed> $options
     */
    protected function resolveRemoteWsdl(string $wsdlUrl, array $options): string
    {
        $hash    = hash('sha256', $wsdlUrl);
        $baseDir = (string) config('gserverlesssupport.system_cache_path', CertificateMaterializer::DEFAULT_SYSTEM_CACHE_PATH);
        $dir     = $baseDir.'/wsdl';

        if (!is_dir($dir)) {
            @mkdir($dir, 0o700, true);
        }

        $cachedFile = $dir.'/wsdl_'.$hash.'.wsdl';

        if (!file_exists($cachedFile)) {
            $request = $this->buildPendingRequest();

            if (isset($options['proxy'])) {
                $request->withOptions(['proxy' => $options['proxy']]);
            }
            if (isset($options['verify'])) {
                $request->withOptions(['verify' => $options['verify']]);
            }
            if (isset($options['cert'])) {
                $request->withOptions(['cert' => $options['cert']]);
            }
            if (isset($options['ssl_key'])) {
                $request->withOptions(['ssl_key' => $options['ssl_key']]);
            }

            $response = $request->get($wsdlUrl);

            if (!$response->successful()) {
                throw new \SoapFault('WSDL', \sprintf('Failed to load WSDL from [%s]: HTTP %d', $wsdlUrl, $response->status()));
            }

            $body = $response->body();

            // Rewrite relative schemaLocation and location attributes in <import> and <include>
            $body = (string) preg_replace_callback(
                '/(<(?:[a-zA-Z0-9_-]+:)?(?:import|include)\b[^>]*\b(?:schemaLocation|location)=["\'])([^"\']+)(["\'])/i',
                function (array $matches) use ($wsdlUrl): string {
                    $resolved = $this->resolveRelativeUrl($wsdlUrl, $matches[2]);

                    return $matches[1].$resolved.$matches[3];
                },
                $body
            );

            $temp = $cachedFile.'.'.bin2hex(random_bytes(8)).'.tmp';
            file_put_contents($temp, $body);
            @chmod($temp, 0o600);
            @rename($temp, $cachedFile);
        }

        return $cachedFile;
    }

    /**
     * Resolve a relative schemaLocation or import URL against the base WSDL URL.
     */
    protected function resolveRelativeUrl(string $baseUrl, string $relative): string
    {
        if (str_starts_with($relative, 'http://') || str_starts_with($relative, 'https://')) {
            return $relative;
        }

        $parts  = parse_url($baseUrl);
        $scheme = $parts['scheme'] ?? 'https';
        $host   = $parts['host']   ?? '';
        $port   = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path   = $parts['path']   ?? '/';

        if (str_starts_with($relative, '//')) {
            return $scheme.':'.$relative;
        }

        if (str_starts_with($relative, '/')) {
            return \sprintf('%s://%s%s%s', $scheme, $host, $port, $relative);
        }

        $dir = \dirname($path);
        if ('.' === $dir || '/' === $dir) {
            $dir = '';
        }

        $combinedPath = ('' !== $dir ? $dir.'/' : '/').$relative;
        $segments     = [];
        foreach (explode('/', $combinedPath) as $seg) {
            if ('' === $seg || '.' === $seg) {
                continue;
            }
            if ('..' === $seg) {
                array_pop($segments);
            } else {
                $segments[] = $seg;
            }
        }

        return \sprintf('%s://%s%s/%s', $scheme, $host, $port, implode('/', $segments));
    }

    /**
     * Check if a response body contains a SOAP Fault XML envelope.
     */
    protected function isSoapFaultXml(?string $body): bool
    {
        if (empty($body)) {
            return false;
        }

        return 1 === preg_match('/<([a-zA-Z0-9_-]+:)?Fault[>\s]/i', $body);
    }

    /**
     * Record wire headers and body for last request and response tracking.
     */
    protected function recordWireMetadata(Response $response, string $location): void
    {
        $psrResponse = $response->toPsrResponse();

        // 1. Response wire formatting
        $statusLine = \sprintf(
            "HTTP/%s %d %s\r\n",
            $psrResponse->getProtocolVersion(),
            $response->status(),
            $response->reason()
        );

        $resHeaders = '';
        foreach ($psrResponse->getHeaders() as $name => $values) {
            foreach ($values as $val) {
                $resHeaders .= \sprintf("%s: %s\r\n", $name, $val);
            }
        }

        $this->lastResponseHeaders = $statusLine.$resHeaders;
        $this->lastResponseBody    = $response->body();

        // 2. Request wire formatting
        $psrRequest = $response->transferStats?->getRequest();
        if ($psrRequest) {
            $reqLine = \sprintf(
                "%s %s HTTP/%s\r\n",
                $psrRequest->getMethod(),
                $psrRequest->getRequestTarget(),
                $psrRequest->getProtocolVersion()
            );

            $reqHeaders = '';
            foreach ($psrRequest->getHeaders() as $name => $values) {
                foreach ($values as $val) {
                    $reqHeaders .= \sprintf("%s: %s\r\n", $name, $val);
                }
            }

            $this->lastRequestHeaders = $reqLine.$reqHeaders;
        } else {
            $parsedPath = parse_url($location, PHP_URL_PATH) ?? '/';
            $query      = parse_url($location, PHP_URL_QUERY);
            if (null !== $query && '' !== $query) {
                $parsedPath .= '?'.$query;
            }

            $this->lastRequestHeaders = \sprintf("POST %s HTTP/1.1\r\nHost: %s\r\n", $parsedPath, parse_url($location, PHP_URL_HOST) ?? '');
        }
    }
}
