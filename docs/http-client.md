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

## The 4-Tier Configuration Architecture

To handle everything from simple Google IAP services to complex multi-tenant, multi-brand integrations where credentials change dynamically per incoming request, `GServerlessSupportLaravel` supports **four configuration tiers**:

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│ Tier 1: Global Declarative Map (config/auth.php or config/gserverlesssupport.php)│
│ Best for: 1:1 host maps, Google IAP OIDC, Google APIs, single-tenant ERPs       │
└────────────────────────────────────────┬────────────────────────────────────────┘
                                         │
┌────────────────────────────────────────┴────────────────────────────────────────┐
│ Tier 2: Request-Scoped Runtime Registry (OutboundAuth::registerForRequest)       │
│ Best for: Multi-brand / multi-theme routes (ConfigController / Route Middleware)│
└────────────────────────────────────────┬────────────────────────────────────────┘
                                         │
┌────────────────────────────────────────┴────────────────────────────────────────┐
│ Tier 3: Scoped Http Client Macros (Http::macro / feature ServiceProvider)       │
│ Best for: Domain service classes with co-located service credentials            │
└────────────────────────────────────────┬────────────────────────────────────────┘
                                         │
┌────────────────────────────────────────┴────────────────────────────────────────┐
│ Tier 4: Per-Request DTOs (Http::withOutboundAuth(OutboundAuth::...))             │
│ Best for: Inline ad-hoc calls, dynamic per-order credentials, testOrder toggles │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

### Tier 1: Global Declarative Host Map

Define static host mappings in `config/auth.php` (under `middleware.audience`):

```php
'audience' => [
    // Exact hostname mapping (Google OIDC)
    'orders.example.com'        => '123456789-abcdef.apps.googleusercontent.com',

    // OAuth2 Bearer token (Google APIs / Cloud Storage)
    'storage.googleapis.com'    => 'oauth2',

    // Path-specific audience mapping on the same host
    'service.internal.net/v1/*' => 'v1-audience.apps.googleusercontent.com',
    'service.internal.net/v2/*' => 'v2-audience.apps.googleusercontent.com',
    'service.internal.net'      => 'default-audience.apps.googleusercontent.com',

    // Wildcard host mapping
    '*.cloudfunctions.net'      => 'cloudfunctions-audience.apps.googleusercontent.com',
    '*.internal.net'            => 'internal-audience.apps.googleusercontent.com',
],
```

---

### Tier 2: Request-Scoped Runtime Registration (Route Middleware / Controller Hook)

In multi-tenant or multi-brand services, an incoming request carries contextual parameters (e.g. `brand=buymobiles` vs `brand=affordable`, or `testOrder=true` vs `live`). A static global hostname map cannot know which credentials to use for `api.partner.com`.

Use `OutboundAuth::registerForRequest()` inside a **Route Group Middleware** or a **Base Controller**:

```php
namespace App\Http\Middleware;

use AffordableMobiles\GServerlessSupportLaravel\Auth\OutboundAuth;
use Closure;
use Illuminate\Http\Request;

class BindConnectorAuthMiddleware
{
    public function handle(Request $request, Closure $next, string $connectorName)
    {
        // 1. Resolve credentials dynamically based on incoming brand / theme / test flags
        $themeCode = $request->input('themeCode', 'default');
        $config    = config("{$themeCode}-{$connectorName}.connectors.{$connectorName}");

        // 2. Register the connector's outbound auth for this request lifecycle
        if (!empty($config['auth'])) {
            OutboundAuth::registerForRequest('api.partner.com/*', $config['auth']);
        }

        return $next($request);
    }
}
```

Apply the middleware to your route group in `routes/api.php`:

```php
Route::prefix('/partner/v1')
    ->middleware('connector.auth:partner')
    ->group(function () {
        Route::post('/orders', [PartnerOrderController::class, 'store']);
    });
```

Downstream service classes and models simply call `Http::post('https://api.partner.com/orders', ...)` with **zero auth boilerplate in the model** and **zero configuration pollution in `config/auth.php`**. `HttpClientServiceProvider` automatically purges the request-scoped registry when the request terminates.

---

### Tier 3: Scoped Laravel Http Client Macros

For dedicated domain clients co-located in service providers, register named `Http` macros:

```php
namespace App\Providers;

use AffordableMobiles\GServerlessSupportLaravel\Auth\OutboundAuth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

class PartnerIntegrationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Http::macro('partnerClient', function () {
            return Http::withOutboundAuth(OutboundAuth::clientCredentials(
                tokenUrl: config('services.partner.token_url'),
                clientId: config('services.partner.client_id'),
                clientSecret: config('services.partner.client_secret'),
            ))->baseUrl(config('services.partner.base_url'));
        });
    }
}
```

Usage anywhere in your application:

```php
$response = Http::partnerClient()->post('/v1/shipments', $shipmentData);
```

---

### Tier 4: Per-Request DTOs & Options

For ad-hoc calls, dynamic per-order credentials, or one-off scripts, pass an authenticator directly via `Http::withOutboundAuth()`:

```php
use AffordableMobiles\GServerlessSupportLaravel\Auth\OutboundAuth;
use Illuminate\Support\Facades\Http;

$response = Http::withOutboundAuth(OutboundAuth::password(
    tokenUrl: 'https://auth.example.com/oauth/token',
    clientId: $apiKey,
    clientSecret: $apiSecret,
    username: $username,
    password: $password,
))->post('https://api.example.com/v1/invitations', $payload);
```

Or when using raw Guzzle:

```php
$response = $guzzle->post('https://api.example.com/v1/invitations', [
    'outbound_auth' => OutboundAuth::password(...),
    'json'          => $payload,
]);
```

> 💡 **Why `outbound_auth`?**
> When passing custom auth configurations to Guzzle, use `'outbound_auth'` rather than `'auth'`. Guzzle's native `Client::applyOptions()` intercepts array configs in `$options['auth']` before middleware runs, treating them as Basic Auth. Using `'outbound_auth'` (or `Http::withOutboundAuth()`) ensures custom authenticators pass cleanly to `AuthTokenMiddleware`.

---

## Full Configuration Reference

In `config/gserverlesssupport.php`:

```php
'http-client' => [
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
