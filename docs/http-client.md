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
2. **Remote Server Errors (`UpstreamServerException`)**:
   Upstream 500, 502, 503, and 504 errors report `UpstreamServerException`.
3. **Critical Authorization & Quota Errors (`UpstreamClientException`)**:
   By default, `401 Unauthorized`, `403 Forbidden`, and `429 Too Many Requests` are reported. Normal expected business responses (such as `404 Not Found` or `422 Unprocessable Entity`) are ignored unless explicitly configured for a specific host.

---

### Google Cloud Error Reporting Aggregation Mechanics

Google Cloud Error Reporting clusters errors using:
- **`serviceContext.service`** (App Engine / Cloud Run service name)
- **Exception Class Name**
- **The Top Application Stack Frame**
- **The First Line of the Exception Message**

If an exception message includes dynamic timestamps, UUIDs, or raw response bodies on its first line, Error Reporting treats every occurrence as a unique group, creating thousands of un-aggregated alerts.

`UpstreamHttpException` solves this by automatically structuring its message into two distinct sections:

```
[api.example.com] POST /v1/transactions -> 503 Service Unavailable
Response summary:
{"error": "database_overloaded", "retry_after": 30}
```

- **Line 1 (Deterministic Group Signature)**:
  `[<host>] <METHOD> <path> -> <status> <reason>`
  This guarantees that all 503 responses for that endpoint collapse into **one unified incident in Error Reporting**.
- **Line 2+ (Diagnostic Response Summary)**:
  Using `GuzzleHttp\Psr7\Message::bodySummary($response, 250)`, the exception safely captures a truncated body summary up to 250 characters. The stream is automatically rewound so calling application code can still read the response body. This diagnostic context appears in Error Reporting's details drawer for immediate root-cause analysis without log archaeology.

---

### Application Exception Handling & Deduplication

In client applications, developers should write clean domain code, translating HTTP failures into user-facing responses without manual reporting calls:

```php
try {
    $response = $this->http->post($endpoint, $data);
} catch (ConnectionException $e) {
    // Upstream transport error was already reported by the listener.
    // Wrap and throw a sanitized domain exception for the API consumer:
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

    // 2. Prevent domain wrapper double-reporting: if a domain exception wraps a
    // raw HttpClientException that was already reported, do not report the wrapper
    $exceptions->dontReportWhen(static function (\Throwable $e): bool {
        return $e->getPrevious() instanceof \Illuminate\Http\Client\HttpClientException;
    });
})
```

---

## Outbound Authentication

### Declarative Pattern Matching with UriMatcher

`AuthTokenMiddleware` matches outbound requests against your audience configuration using `UriMatcher`. Rules are scored by specificity so the most specific pattern always wins:

1. **Exact Host + Exact Path**: `api.example.com/v1/checkout` (Highest precedence)
2. **Exact Host + Wildcard Path**: `api.example.com/v1/*`
3. **Exact Host Catch-All**: `api.example.com` or `api.example.com/*`
4. **Wildcard Host + Path**: `*.internal.net/v1/*`
5. **Wildcard Host Catch-All**: `*.internal.net`
6. **Global Fallback**: `*` (Lowest precedence)

Example configuration in `config/auth.php` (under `middleware.audience`):

```php
'audience' => [
    // Exact hostname mapping (Google OIDC)
    'orders.example.com'          => '123456789-abcdef.apps.googleusercontent.com',

    // OAuth2 Bearer token (Google APIs / Cloud Storage)
    'storage.googleapis.com'      => 'oauth2',

    // Path-specific audience mapping on the same host
    'service.internal.net/v1/*'   => 'v1-audience.apps.googleusercontent.com',
    'service.internal.net/v2/*'   => 'v2-audience.apps.googleusercontent.com',
    'service.internal.net'        => 'default-audience.apps.googleusercontent.com',

    // Wildcard host mapping
    '*.cloudfunctions.net'        => 'cloudfunctions-audience.apps.googleusercontent.com',
    '*.internal.net'              => 'internal-audience.apps.googleusercontent.com',

    // Nested path map syntax
    'gateway.example.com' => [
        '/admin/*' => 'admin-audience.apps.googleusercontent.com',
        '*'        => 'public-audience.apps.googleusercontent.com',
    ],
],
```

---

### Supported Authentication Strategies

Outbound authentication is organized into reusable strategies:

| Strategy | Standard | Description |
| :--- | :--- | :--- |
| **Google OIDC** | OpenID Connect | Fetches ID tokens from Google metadata server or IAM service account. |
| **Google OAuth2** | OAuth 2.0 | Fetches access tokens via Google metadata server or Domain-Wide Delegation (`GCEDWDCredentials`). |
| **OAuth2 Client Credentials** | RFC 6749 §4.4 | Supports `client_secret_basic` (HTTP Basic Auth), `client_secret_post` (form body), and custom JSON auth endpoints. |
| **OAuth2 JWT Bearer** | RFC 7523 | Generates and signs client assertions using Google IAM (`signBlob` / `signJwt`), Cloud KMS asymmetric keys, or PEM keys. |
| **OAuth2 Password** | RFC 6749 §4.3 | Resource Owner Password Credentials with credential-isolated caching. |
| **OAuth1 TBA** | RFC 5849 | Token-Based Authentication with HMAC-SHA256 request signing and query normalization. |

---

### Token Caching & Lifecycle

Outbound tokens are cached in the serverless instance-scoped cache store (`/tmp/cache/GServerlessSupportLaravel`):

1. **Collision-Free SHA-256 Keys**:
   Cache keys are generated from a SHA-256 hash of `[driver, tokenUrl, clientId, scopes, subject]`. This prevents token collision across multiple tenants, credentials, or themes calling the same provider endpoint.
2. **Dynamic TTL with Safety Buffer**:
   Tokens are cached for `max(30, $expiresIn - 120)` seconds. The 120-second buffer guarantees that tokens never expire mid-flight during slow outbound requests.
3. **Automatic 401 Re-Auth Retry**:
   If an external provider revokes a token early and returns `401 Unauthorized`, the middleware automatically evicts the cached token from the instance-scoped store once and replays the request with a freshly negotiated token.

---

### RFC 5849 OAuth 1.0a Token-Based Authentication (TBA)

For providers requiring Token-Based Authentication (such as ERP systems and RESTlets):

#### Standards Compliance (RFC 5849)

In RFC 5849 §3.4.1.3 (Parameter Normalization) and §3.5.1 (Authorization Header):

1. **Signature Base String**:
   All request parameters must be collected, sorted, and included in the HMAC-SHA256 signature calculation. This includes:
   - Request HTTP method (`GET`, `POST`, `PUT`, `DELETE`).
   - Normalized URI scheme, host, and path (`https://api.example.com/restlet`).
   - **All query string parameters** (e.g. `?script=123&deploy=1&custom_param=foo`).
   - All `oauth_*` parameters (except `oauth_signature`).
2. **Authorization Header**:
   **Only** parameters beginning with `oauth_` and the `realm` parameter are placed into the `Authorization: OAuth ...` header. **Query parameters must remain in the URL query string and must NOT be duplicated in the header.**

The built-in OAuth 1.0a driver adheres strictly to RFC 5849. It automatically parses query strings from the PSR-7 request, incorporates them into the HMAC signature, and generates standard `Authorization: OAuth realm="...", oauth_consumer_key="...", ...` headers.

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
})
```
