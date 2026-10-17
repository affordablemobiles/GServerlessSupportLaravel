# Laravel HTTP Client Integration

`HttpClientServiceProvider` provides unified Google Serverless integration for Laravel's native HTTP Client (`Illuminate\Support\Facades\Http`).

It bundles three key outbound HTTP operational concerns into a single opt-in service provider:

1. **Automatic Connection Retries**: Automatically retries transient serverless network and TCP connection timeouts using exponential backoff with jitter via `GuzzleRetryMiddleware`.
2. **Google Service-to-Service Authentication**: Automatically injects Google IAP/OIDC or OAuth2 Bearer tokens for internal microservices based on your domain audience map via `AuthTokenMiddleware`.
3. **Automated SRE Error Telemetry**: Intercepts outbound HTTP transport drops and upstream provider errors (5xx server outages and critical 4xx authorization/quota failures), reporting them directly to **Google Cloud Error Reporting** with structured signatures designed for clean, deterministic incident aggregation.

---

## Table of Contents

- [Quick Start](#quick-start)
- [Architecture & SRE Telemetry](#architecture--sre-telemetry)
  - [The Operational Problem with Ad-Hoc Logging](#the-operational-problem-with-ad-hoc-logging)
  - [How Outbound Telemetry Works in Practice](#how-outbound-telemetry-works-in-practice)
  - [Google Cloud Error Reporting Aggregation Mechanics](#google-cloud-error-reporting-aggregation-mechanics)
  - [Application Exception Handling & Deduplication](#application-exception-handling--deduplication)
- [Outbound Authentication](#outbound-authentication)
  - [Declarative Pattern Matching with UriMatcher](#declarative-pattern-matching-with-urimatcher)
  - [Supported Authentication Strategies](#supported-authentication-strategies)
  - [Token Caching & Lifecycle](#token-caching--lifecycle)
  - [RFC 5849 OAuth 1.0a Token-Based Authentication (TBA)](#rfc-5849-oauth-10a-token-based-authentication-tba)
- [Full Configuration Reference](#full-configuration-reference)

---

## Quick Start

### Register the Service Provider

Add `HttpClientServiceProvider` to your application's providers list.

In Laravel 11/12 (`bootstrap/providers.php`):

```php
return [
    App\Providers\AppServiceProvider::class,
    // ...
    AffordableMobiles\GServerlessSupportLaravel\Integration\HttpClient\HttpClientServiceProvider::class,
];
```

Or in `config/app.php`:

```php
'providers' => [
    // ...
    AffordableMobiles\GServerlessSupportLaravel\Integration\HttpClient\HttpClientServiceProvider::class,
],
```

Once registered, all outbound requests executed via `Http::...` or `app(Illuminate\Http\Client\Factory::class)` automatically benefit from retries, auth injection, and error reporting telemetry.

---

## Architecture & SRE Telemetry

### The Operational Problem with Ad-Hoc Logging

In distributed serverless architectures, external API and microservice calls are frequent points of failure. Historically, applications handle outbound failures with ad-hoc logging:

```php
// Ad-hoc pattern:
if (!$response->successful()) {
    Log::warning('Outbound API request failed', ['status' => $response->status()]);
    throw new CustomProviderException('The remote provider returned an error.');
}
```

This pattern creates three major blindspots for Site Reliability Engineering (SRE):

1. **Invisible Outages in Error Tracking**:
   Google Cloud Error Reporting only ingests log entries with severity `ERROR`, `CRITICAL`, `ALERT`, or uncaught exception stack traces. `Log::warning()` entries are completely ignored. An ongoing third-party outage produces zero alerts in Error Reporting.
2. **Alert Fragmentation**:
   If every client throws its own domain exception (`BillingProviderException`, `AddressProviderException`, `InventoryProviderException`), Error Reporting creates a distinct, siloed error group for each exception class. SRE cannot easily detect that multiple internal services are failing against the same external provider.
3. **Loss of Root-Cause Details**:
   When an external vendor returns HTTP `401`, `403`, `429`, or `500`, their response body typically contains the precise diagnostic cause (e.g. `{"error": "license_expired", "detail": "Account quota exceeded"}`). Catching and throwing a generic domain exception strips this payload, forcing engineers to manually reproduce the failure or contact vendor support.

---

### How Outbound Telemetry Works in Practice

`OutboundHttpTelemetryListener` subscribes to Laravel's native HTTP client lifecycle events:

```
[ Outbound HTTP Request ]
            │
            ├─► Connection Drops / DNS / TCP Timeouts
            │   └─► Dispatches ConnectionFailed event
            │       └─► OutboundHttpTelemetryListener reports raw ConnectionException
            │           (Aggregated platform-wide by cURL error code in Error Reporting)
            │
            └─► Completed HTTP Response
                └─► Dispatches ResponseReceived event
                    └─► OutboundHttpTelemetryListener inspects HTTP status & host rules
                        ├─► 2xx / Expected 4xx (404, 422) ──► Passed through silently
                        ├─► 5xx Server Outages ────────────► Reports UpstreamServerException
                        └─► Critical 4xx (401, 403, 429) ──► Reports UpstreamClientException
```

1. **Transport / Network Failures (`ConnectionException`)**:
   DNS lookup failures, cURL error 28 connect timeouts, and Cloud NAT port exhaustion report the raw `Illuminate\Http\Client\ConnectionException`. Error Reporting clusters these by cURL error code, providing SRE with a clear platform-wide picture of egress network health.
2. **Transient Retries are Non-Disruptive**:
   When `GuzzleRetryMiddleware` retries a connection timeout, it logs an informative `Log::info` entry during the delay. If the retry succeeds on attempt 2, 3, or 4, **zero exceptions are reported to Error Reporting**. SRE is only alerted if all attempts are completely exhausted.
3. **Remote Server Errors (`UpstreamServerException`)**:
   Upstream 500, 502, 503, and 504 errors report `UpstreamServerException`.
4. **Critical Authorization & Quota Errors (`UpstreamClientException`)**:
   By default, `401 Unauthorized`, `403 Forbidden`, and `429 Too Many Requests` are reported. Normal expected business responses (such as `404 Not Found` or `422 Unprocessable Entity`) are ignored unless explicitly configured for a specific host.

---

### Google Cloud Error Reporting Aggregation Mechanics

Google Cloud Error Reporting clusters errors using:
- **`serviceContext.service`** (App Engine / Cloud Run service name)
- **Exception Class Name**
- **The Top Application Stack Frame**
- **The First Line of the Exception Message**

If an exception message includes dynamic identifiers (such as `/customers/12345/orders/ORD-98234-XYZ`) on its first line, Error Reporting creates a distinct error group for every single customer or order reference, causing alert explosion.

`UpstreamHttpException` eliminates this cardinality risk by cleanly structuring its message:

```
[api.example.com] POST -> 503 Service Unavailable
URL: https://api.example.com/customers/12345/orders/ORD-98234-XYZ/status
Response summary:
{"error": "database_overloaded", "retry_after": 30}
```

- **Line 1 (Zero-Cardinality Signature)**:
  `[<host>] <METHOD> -> <status> <reason>`
  This guarantees that all 503 responses for that host and HTTP verb collapse into **one unified incident in Error Reporting**, grouped alongside the application caller's stack frame.
- **Line 2 (Diagnostic URL)**:
  Contains the full, exact request URL including path and query string.
- **Line 3+ (Diagnostic Response Summary)**:
  Using `GuzzleHttp\Psr7\Message::bodySummary($response, 250)`, the exception safely captures a truncated body summary up to 250 characters. The stream is automatically rewound so calling application code can still read the response body.
- **Structured Context Array**:
  `UpstreamHttpException` implements `context()`, providing structured keys (`http_host`, `http_method`, `http_url`, `http_path`, `http_status`) that Monolog and Google Cloud Logging ingest into filterable JSON payloads under `jsonPayload.context`.

---

### Application Exception Handling & Deduplication

In client applications, developers should write clean domain code, translating HTTP failures into user-facing responses without manual reporting calls:

```php
try {
    $response = $this->http->post($endpoint, $data);
} catch (ConnectionException $e) {
    // Upstream transport error was already reported by the listener.
    throw new ServiceUnavailableException('The service is temporarily unavailable.', previous: $e);
}

if (!$response->successful()) {
    // Upstream 5xx/4xx error was already reported by the listener.
    throw new ProviderException('The provider returned an invalid response.', previous: $response->toException());
}
```

To prevent double-reporting the outer domain wrapper, configure your application's exception handler in `bootstrap/app.php`:

```php
->withExceptions(static function (Exceptions $exceptions): void {
    // 1. In-session deduplication: drops duplicate reports of the exact same exception instance
    $exceptions->dontReportDuplicates();

    // 2. Prevent domain wrapper double-reporting: if a domain exception wraps an
    // already-reported HttpClientException, don't report the outer wrapper
    $exceptions->dontReportWhen(static function (\Throwable $e): bool {
        return $e->getPrevious() instanceof \Illuminate\Http\Client\HttpClientException;
    });
})
```

---

## Outbound Authentication & The 4-Tier Configuration Architecture

`HttpClientServiceProvider` seamlessly integrates `AuthTokenMiddleware` with Laravel's `Http` client. Authentication can be configured at any of four tiers based on application needs:

1. **Tier 1 (Global Declarative Map)**: In `config/auth.php` (under `middleware.audience`) for static 1:1 host mappings, Google OIDC, OAuth2, and wildcards (`*.internal.net`).
2. **Tier 2 (Request-Scoped Runtime Registry)**: Via `OutboundAuth::registerForRequest('api.partner.com/*', $config)` in Route Middleware or Base Controllers for multi-brand/multi-theme credentials that change per incoming request.
3. **Tier 3 (Scoped Http Client Macros)**: Via `Http::macro('partnerClient', fn () => Http::withOutboundAuth(...))` in service providers.
4. **Tier 4 (Per-Request DTOs)**: Directly inline via `Http::withOutboundAuth(OutboundAuth::...)`.

> 📖 **Full Authentication Documentation:**
> See **[docs/guzzle-auth.md](guzzle-auth.md#configuration-architecture-the-4-tiers)** for comprehensive guides, middleware code examples for each tier, driver reference (Google OIDC/OAuth2, RFC 6749 OAuth 2.0, RFC 5849 OAuth 1.0a TBA, API keys, HTTP Basic, WSSE, Payload Signing, mTLS), and keyless Google IAM/KMS signing.

> 💡 **Why `withOutboundAuth()`?**
> When passing custom auth configurations to Guzzle or Laravel's HTTP client, use `'outbound_auth'` (or the `Http::withOutboundAuth()` macro) rather than `'auth'`. Guzzle's native `Client::applyOptions()` intercepts array configs in `$options['auth']` before middleware runs, treating them as Basic Auth. Using `'outbound_auth'` ensures custom authenticators pass cleanly to `AuthTokenMiddleware`.

---

## Full Configuration Reference

In `config/gserverlesssupport.php`:

```php
'http-client' => [
    // Global default Guzzle / cURL options applied via Http::globalOptions()
    'options' => [
        'timeout'         => env('G_SERVERLESS_HTTP_TIMEOUT', 10),
        'connect_timeout' => env('G_SERVERLESS_HTTP_CONNECT_TIMEOUT', 2),
        'verify'          => env('G_SERVERLESS_HTTP_VERIFY', true),
        // Optional DNS resolution overrides (maps to CURLOPT_RESOLVE, e.g. 'example.com:443:127.0.0.1')
        'resolve'         => array_filter(explode(',', (string) env('G_SERVERLESS_HTTP_RESOLVE', ''))),
    ],

    // Automatically retry connection/network timeouts using exponential backoff
    'retry' => [
        'enabled' => env('G_SERVERLESS_HTTP_RETRY_ENABLED', true),
    ],

    // Automatically inject Google OIDC/OAuth2 tokens based on audience map
    'auth' => [
        'enabled' => env('G_SERVERLESS_HTTP_AUTH_ENABLED', true),
    ],

    // Outbound error reporting telemetry
    'telemetry' => [
        'enabled'    => env('G_SERVERLESS_HTTP_TELEMETRY_ENABLED', true),
        'report_5xx' => env('G_SERVERLESS_HTTP_REPORT_5XX', true),
        'report_4xx' => [401, 403, 429],

        // Per-host and per-path overrides (supports exact hostnames, paths, and wildcards)
        'hosts' => [
            // Example: Track custom 400 Bad Request for a specific provider endpoint
            'api.example.com/v1/validate' => [
                'report_5xx' => true,
                'report_4xx' => [400, 401, 403, 429],
            ],

            // Example: Disable telemetry for noisy healthcheck endpoints
            'api.example.com/health' => [
                'enabled' => false,
            ],

            // Example: Disable 5xx reporting for internal maintenance domain
            '*.internal.net' => [
                'report_5xx' => false,
            ],

            // Example: Completely silence untracked / mock endpoint
            'mock.example.com' => [
                'enabled' => false,
            ],
        ],
    ],
],
```

---

## SOAP Client Support

The package provides a native, `Http`-backed SOAP client (`AffordableMobiles\GServerlessSupportLaravel\Integration\Soap\SoapClient`) extending PHP's core `\SoapClient`.

It routes all SOAP requests through Laravel's HTTP client pipeline, providing:
- **Serverless WSDL Caching**: Automatically sets `soap.wsdl_cache_dir` in tmpfs (`/tmp/cache/.../wsdl`) with `WSDL_CACHE_BOTH`.
- **Remote WSDL Fetching**: Remote WSDLs are downloaded through Laravel's HTTP client, inheriting configured proxies, retries, and SSL verification.
- **W3C SOAP 1.1 & SOAP 1.2 Compliance**: Correctly sets `Content-Type` and `SOAPAction` headers for SOAP 1.1 (`text/xml; charset=utf-8`) and SOAP 1.2 (`application/soap+xml; charset=utf-8; action="..."`).
- **Telemetry & Error Reporting**: Network timeouts and 5xx transport crashes are reported to Error Reporting. Business/validation `<soap:Fault>` envelopes (client/sender faults) are parsed into standard `\SoapFault` instances and filtered out of 5xx telemetry alerts.
- **In-Memory mTLS & Outbound Auth**: Seamless integration with `withClientCert()` (using in-memory cURL blobs) and `withOutboundAuth()`.
- **Full Trace & Wire Inspection**: Supports `$client->__getLastRequest()`, `$client->__getLastResponse()`, `$client->__getLastRequestHeaders()`, and `$client->__getLastResponseHeaders()`.

### Basic Usage

Use the `Http::soap()` macro or instantiate `SoapClient` directly:

```php
use Illuminate\Support\Facades\Http;

$client = Http::soap('https://ws.example.com/service.wsdl', [
    'soap_version' => SOAP_1_1,
    'compression'  => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
]);

$response = $client->GetAddressDetails(['postcode' => 'DE1 1AA']);
```

### With Mutual TLS (mTLS)

When accessing endpoints or downloading remote WSDLs protected by mutual TLS, chain the client certificate directly on `Http` before `.soap()` or pass it in `$options`:

```php
// Option A: Chain on Http first so remote WSDL downloads automatically inherit mTLS
$client = Http::withClientCert(
        cert: config('services.partner.cert_pem'),
        key: config('services.partner.key_pem'),
        certPassword: config('services.partner.cert_pass'),
        inMemory: true, // Uses CURLOPT_SSLCERT_BLOB (no disk I/O)
    )
    ->timeout(30)
    ->soap($wsdlUrl, ['soap_version' => SOAP_1_2]);

$result = $client->SubmitApplication($payload);

// Option B: Pass directly in SoapClient options
$client = Http::soap($wsdlUrl, [
    'soap_version' => SOAP_1_2,
    'client_cert'  => [
        'cert'         => config('services.partner.cert_pem'),
        'key'          => config('services.partner.key_pem'),
        'certPassword' => config('services.partner.cert_pass'),
        'inMemory'     => true,
    ],
    'timeout'      => 30,
]);
```

### With WS-Security (WSSE) or Basic Auth

```php
use AffordableMobiles\GServerlessSupportLaravel\Auth\OutboundAuth;

// OASIS WS-Security UsernameToken (accepts secret: or password:)
$client = Http::withOutboundAuth(OutboundAuth::wsse(
        username: config('services.partner.user'),
        secret: config('services.partner.pass'),
    ))
    ->soap($wsdlUrl);

// Basic HTTP Auth
$client = Http::withOutboundAuth(OutboundAuth::basic(
        username: config('services.partner.user'),
        password: config('services.partner.pass'),
    ))
    ->soap($wsdlUrl);
```

### Custom Headers & Timeouts

```php
$client = Http::soap($wsdlPath)
    ->withHeaders([
        'X-Partner-ID' => 'ACME-CORP',
    ])
    ->withTimeout(45)
    ->withConnectTimeout(10);
```

### Transport Exceptions vs SOAP Faults

```php
use AffordableMobiles\GServerlessSupportLaravel\Integration\Soap\Exceptions\SoapTransportException;

try {
    $response = $client->PerformOperation($request);
} catch (SoapTransportException $e) {
    // Upstream network failure, DNS error, or non-SOAP HTTP 502/503/504 gateway crash
    Log::error('SOAP transport failed: ' . $e->getMessage());
    $rawResponse = $client->__getLastResponse();
} catch (\SoapFault $e) {
    // Application-level SOAP Fault returned by remote endpoint (e.g., validation or auth failure)
    Log::warning('SOAP fault returned: ' . $e->getMessage(), [
        'faultcode' => $e->faultcode,
        'faultstring' => $e->faultstring,
        'detail' => $e->detail,
    ]);
}
```

---

## Egress Proxying Best Practices (`https_proxy` & `no_proxy`)

For serverless applications routing outbound requests through an internal VPC Squid or NAT proxy (e.g. for static egress IP whitelisting), standard environment variables should be defined in `app.yaml` or Cloud Run environment configurations:

```yaml
env_variables:
  https_proxy: 'http://172.20.250.10:3128'
  http_proxy: 'http://172.20.250.10:3128'
  no_proxy: '.googleapis.com,metadata.google.internal'
```

- **Native Resolution**: Guzzle and `libcurl` natively respect `https_proxy`, `http_proxy`, and `no_proxy` (both lowercase and uppercase).
- **Google Service Isolation**: The `no_proxy` directive ensures calls to Google APIs (`.googleapis.com`) and instance metadata (`metadata.google.internal`) completely bypass the egress proxy.
- **Per-Host Proxy Normalization**: If a specific partner or host rule requires a dedicated proxy (e.g. `'proxy' => '172.20.250.10:3128'`), `AuthTokenMiddleware` automatically normalizes bare `host:port` strings by prepending `http://` if no scheme is present.

---

## Development Overrides: DNS Resolution & TLS Verification

To test against local or staging endpoints without altering `/etc/hosts` or disabling TLS verification globally:

### Per-Host Scoping in Audience Map
You can configure `'resolve'` and `'verify'` on specific host entries:

```php
'middleware' => [
    'audience' => [
        'partner-api.example.com' => [
            'audience' => '...',
            'verify'   => false, // Disable SSL verification for this host only
            'resolve'  => 'partner-api.example.com:443:10.0.0.5', // Direct DNS to local/staging IP
        ],
    ],
],
```

### Global Scoping
Or globally via `config/gserverlesssupport.php` (applied via `Http::globalOptions()`):

```php
'http-client' => [
    'options' => [
        'verify'  => env('G_SERVERLESS_HTTP_VERIFY', true),
        'resolve' => array_filter(explode(',', (string) env('G_SERVERLESS_HTTP_RESOLVE', ''))),
    ],
],
```

---

## Convenience Macros

### `Http::ping(string $url, int $timeout = 2): bool`
Performs a fast, non-redirecting GET request suitable for application health check probes:

```php
use Illuminate\Support\Facades\Http;

if (!Http::ping('https://internal-service.amlsso.uk/health', timeout: 2)) {
    // Health probe failed or timed out
}
```

### `Response::xml(int $options = LIBXML_NOCDATA): \SimpleXMLElement|false`
Parses XML response payloads directly, mirroring `$response->json()`:

```php
$response = Http::get('https://api.example.com/catalog.xml');

$xml = $response->xml();
if ($xml) {
    echo $xml->item[0]->title;
}
```
