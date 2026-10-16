# Outbound Authentication & Token Management

`AuthTokenMiddleware` provides unified, standards-compliant outbound authentication for both Guzzle and Laravel's HTTP Client (`Http::...`).

It supports Google IAP/OIDC, Google OAuth2, OAuth 2.0 (RFC 6749 / RFC 7523), OAuth 1.0a Token-Based Authentication (RFC 5849), and custom API keys.

---

## Table of Contents

- [Overview & Architecture](#overview--architecture)
- [Declarative Configuration](#declarative-configuration)
  - [Audience Map Location](#audience-map-location)
  - [URL & Path Matching with UriMatcher](#url--path-matching-with-urimatcher)
- [Supported Authentication Strategies](#supported-authentication-strategies)
  - [1. Google OIDC (IAP & Cloud Run)](#1-google-oidc-iap--cloud-run)
  - [2. Google OAuth2 (Google APIs)](#2-google-oauth2-google-apis)
  - [3. OAuth 2.0 Client Credentials (RFC 6749 §4.4)](#3-oauth-20-client-credentials-rfc-6749-44)
  - [4. OAuth 2.0 JWT Bearer (RFC 7523)](#4-oauth-20-jwt-bearer-rfc-7523)
  - [5. OAuth 2.0 Password (RFC 6749 §4.3)](#5-oauth-20-password-rfc-6749-43)
  - [6. OAuth 2.0 Refresh Token (RFC 6749 §6)](#6-oauth-20-refresh-token-rfc-6749-6)
  - [7. OAuth 1.0a Token-Based Authentication (RFC 5849)](#7-oauth-10a-token-based-authentication-rfc-5849)
  - [8. API Key Injection](#8-api-key-injection)
- [Keyless JWT Signing Architecture](#keyless-jwt-signing-architecture)
  - [Tier 1 (Recommended): Google IAM signBlob + Public JWKS](#tier-1-recommended-google-iam-signblob--public-jwks)
  - [Tier 2: Google Cloud KMS Asymmetric Signing](#tier-2-google-cloud-kms-asymmetric-signing)
  - [Tier 3: In-Memory OpenSSL PEM Key](#tier-3-in-memory-openssl-pem-key)
- [Token Caching & Lifecycle](#token-caching--lifecycle)
  - [Collision-Free Caching](#collision-free-caching)
  - [Dynamic TTL & Safety Margin](#dynamic-ttl--safety-margin)
  - [Automatic 401 Re-Authentication Retry](#automatic-401-re-authentication-retry)
- [Per-Request Overrides with OutboundAuth](#per-request-overrides-with-outboundauth)
- [Exception Hierarchy & SRE Visibility](#exception-hierarchy--sre-visibility)

---

## Overview & Architecture

Outbound authentication operates as a pluggable strategy pipeline implementing `RequestAuthenticatorInterface`:

```
Outbound HTTP Request
         │
         ▼
[ AuthTokenMiddleware ]
         │
         ├─► Per-Request 'auth' Option (OutboundAuth DTO) ──► Uses Specified Authenticator
         │
         └─► Default Dynamic Resolution (UriMatcher)
                   │
                   ├─► Match by Host + Path Pattern (config/auth.php)
                   ├─► Fallback to Wildcards (*.internal.net)
                   └─► Auto-Detect Cloud Functions (scheme://host/path OIDC)
```

Requests can be configured either **declaratively** via your application's audience configuration map, or **imperatively per-request** using the `OutboundAuth` helper class.

---

## Declarative Configuration

### Audience Map Location

In `config/gserverlesssupport.php`, configure the config path pointing to your audience mapping array:

```php
return [
    'auth' => [
        'middleware' => [
            'audience_map_location' => 'auth.middleware.audience',
        ],
    ],
];
```

### URL & Path Matching with UriMatcher

In `config/auth.php` (under `middleware.audience`), define your domain and path authentication profiles. `UriMatcher` resolves configurations using specificity precedence:

1. Exact Host + Exact Path (`api.example.com/v1/checkout`)
2. Exact Host + Wildcard Path (`api.example.com/v1/*`)
3. Exact Host Catch-All (`api.example.com`)
4. Wildcard Host + Path (`*.internal.net/v1/*`)
5. Wildcard Host Catch-All (`*.internal.net`)
6. Global Catch-All (`*`)

---

## Supported Authentication Strategies

### 1. Google OIDC (IAP & Cloud Run)

Injects OpenID Connect ID tokens issued by Google metadata server for authenticating against IAP-secured applications or Cloud Run services.

```php
'audience' => [
    // Standard string syntax
    'service.example.com' => '123456789-abcdef.apps.googleusercontent.com',

    // Or explicit driver syntax
    'secure.example.com' => [
        'driver'   => 'google_oidc',
        'audience' => '123456789-abcdef.apps.googleusercontent.com',
    ],
],
```

### 2. Google OAuth2 (Google APIs)

Injects Google OAuth2 access tokens for Google APIs (e.g. Cloud Storage, BigQuery).

```php
'audience' => [
    'storage.googleapis.com' => 'oauth2',
],
```

### 3. OAuth 2.0 Client Credentials (RFC 6749 §4.4)

Exchanges client credentials at an external authorization server for an access token.

```php
'api.example.com' => [
    'driver'        => 'oauth2_client_credentials',
    'token_url'     => 'https://auth.example.com/oauth/v2/token',
    'client_id'     => env('EXAMPLE_CLIENT_ID'),
    'client_secret' => env('EXAMPLE_CLIENT_SECRET'),
    'scope'         => 'read write',
    'auth_method'   => 'client_secret_basic', // 'client_secret_basic', 'client_secret_post', or 'client_secret_json'
    'extra_resource_headers' => [
        'X-Client-ID' => env('EXAMPLE_CLIENT_ID'),
    ],
],
```

### 4. OAuth 2.0 JWT Bearer (RFC 7523)

Generates and signs a JSON Web Token assertion and exchanges it for an access token at the authorization server.

```php
'partner.example.com' => [
    'driver'     => 'oauth2_jwt_bearer',
    'token_url'  => 'https://auth.partner.example.com/oauth2/token',
    'grant_type' => 'client_credentials', // sends client_assertion
    'basic_auth' => env('PARTNER_BASIC_AUTH'), // optional [username, password]
    'signer'     => [
        'driver'          => 'iam',
        'service_account' => 'integration@my-gcp-project.iam.gserviceaccount.com',
    ],
    'claims' => [
        'iss' => 'my-app@my-gcp-project.iam.gserviceaccount.com',
        'sub' => 'my-client-id',
        'aud' => 'https://auth.partner.example.com',
    ],
    'extra_resource_headers' => [
        'X-Vendor-Client-ID' => env('PARTNER_CLIENT_ID'),
    ],
],
```

### 5. OAuth 2.0 Password (RFC 6749 §4.3)

Resource Owner Password Credentials Grant with credential-isolated caching.

```php
'api.example.com' => [
    'driver'        => 'oauth2_password',
    'token_url'     => 'https://auth.example.com/oauth/token',
    'client_id'     => env('EXAMPLE_CLIENT_ID'),
    'client_secret' => env('EXAMPLE_CLIENT_SECRET'),
    'username'      => env('EXAMPLE_USERNAME'),
    'password'      => env('EXAMPLE_PASSWORD'),
],
```

### 6. OAuth 2.0 Refresh Token (RFC 6749 §6)

Exchanges a refresh token for an access token, with support for rotation callbacks.

```php
'ads.example.com' => [
    'driver'        => 'oauth2_refresh_token',
    'token_url'     => 'https://login.example.com/oauth2/v2.0/token',
    'client_id'     => env('ADS_CLIENT_ID'),
    'client_secret' => env('ADS_CLIENT_SECRET'),
    'refresh_token' => env('ADS_REFRESH_TOKEN'),
    'on_token_refreshed' => function (array $tokenData): void {
        // Persist newly rotated refresh token to Secret Manager or database
    },
],
```

### 7. OAuth 1.0a Token-Based Authentication (RFC 5849)

Per-request HMAC-SHA256 signature generation for Token-Based Authentication (such as ERP RESTlets).

```php
'*.restlets.example.com' => [
    'driver'           => 'oauth1_tba',
    'consumer_key'     => env('ERP_CONSUMER_KEY'),
    'consumer_secret'  => env('ERP_CONSUMER_SECRET'),
    'token_id'         => env('ERP_TOKEN_ID'),
    'token_secret'     => env('ERP_TOKEN_SECRET'),
    'realm'            => env('ERP_ACCOUNT_ID'),
    'signature_method' => 'HMAC-SHA256',
],
```

#### RFC 5849 Parameter Normalization
- **Signature Base String (§3.4.1.3)**: Includes the request HTTP method, normalized URL, all query string parameters (`?script=...&deploy=...`), and OAuth parameters.
- **Authorization Header (§3.5.1)**: Emits only `realm` and `oauth_*` parameters in the header. **Query parameters remain in the URL query string and are not duplicated in the header.**

### 8. API Key Injection

Injects static API keys or bearer tokens into headers or query parameters.

```php
'api.thirdparty.com' => [
    'driver' => 'api_key',
    'key'    => env('THIRDPARTY_API_KEY'),
    'name'   => 'X-API-Key',
    'in'     => 'header',
],
```

---

## Keyless JWT Signing Architecture

For RFC 7523 JWT Bearer flows, `GServerlessSupportLaravel` supports three signing tiers:

### Tier 1 (Recommended): Google IAM signBlob + Public JWKS

**Zero Stored Private Keys**: The application runtime builds the unsigned JWT payload and delegates signing to the Google Cloud IAM Credentials API (`projects/-/serviceAccounts/{email}:signBlob`) using its attached service account identity.

1. Provision a dedicated Google Service Account for the integration: `integration-partner@my-project.iam.gserviceaccount.com`.
2. Grant your Cloud Run / App Engine runtime service account `roles/iam.serviceAccountTokenCreator` on that target service account.
3. Configure the signer in your audience profile:
   ```php
   'signer' => [
       'driver'          => 'iam',
       'service_account' => 'integration-partner@my-project.iam.gserviceaccount.com',
   ],
   ```
4. Provide the third party with Google's public JWKS endpoint:
   `https://www.googleapis.com/service_accounts/v1/metadata/jwk/{SERVICE_ACCOUNT_EMAIL}`

> ⚠️ **Critical Requirement for Third Parties**:
> Google automatically rotates system-managed service account signing keys roughly every 15 days. The third-party OAuth server **must dynamically poll and cache the JWKS endpoint** respecting HTTP Cache-Control headers. If a partner requires uploading a static certificate or public key file, do not use Tier 1; use Tier 2 instead.

### Tier 2: Google Cloud KMS Asymmetric Signing

When a third party requires uploading a static public key (`.pem`) or registering a Certificate Signing Request (CSR):

1. Create an asymmetric signing key in Google Cloud KMS (`RSA_SIGN_PKCS1_2048_SHA256`).
2. Export the public key via `gcloud kms keys versions get-public-key` and upload it to the partner portal.
3. Configure `KmsSigner`:
   ```php
   'signer' => [
       'driver'      => 'kms',
       'key_version' => 'projects/my-project/locations/europe-west1/keyRings/integrations/cryptoKeys/partner-key/cryptoKeyVersions/1',
   ],
   ```
Private key material never leaves the Cloud KMS Hardware Security Module (HSM).

### Tier 3: In-Memory OpenSSL PEM Key

If an encrypted or plain PEM private key must be loaded from Secret Manager or environment:

```php
'signer' => [
    'driver'      => 'openssl',
    'private_key' => env('PARTNER_PRIVATE_KEY_PEM'),
    'passphrase'  => env('PARTNER_KEY_PASSPHRASE'),
],
```

Keys are processed entirely in memory via `openssl_sign` and never written to temporary files on disk.

---

## Token Caching & Lifecycle

### Collision-Free Caching
Tokens are stored in the serverless instance-scoped cache (`/tmp/cache/GServerlessSupportLaravel`).
Cache keys are computed using a SHA-256 hash across all identity dimensions:
`outbound_token_<hash(driver, token_url, client_id, scopes, subject, user_hash)>`

This guarantees that multi-tenant or multi-credential calls hitting the same token endpoint never overwrite each other.

### Dynamic TTL & Safety Margin
Tokens are cached for:
$$\text{TTL} = \max(30, \text{expires\_in} - 120)$$

The 120-second safety margin ensures tokens are refreshed before they can expire mid-flight during slow outbound requests.

### Automatic 401 Re-Authentication Retry
If an external provider revokes an access token before its scheduled expiration and returns `401 Unauthorized`, `AuthTokenMiddleware` automatically:
1. Evicts the token from the instance-scoped cache store.
2. Negotiates a fresh token with the provider.
3. Re-authenticates and retries the outbound request once.

---

## Per-Request Overrides with OutboundAuth

For multi-brand or dynamic integrations where credentials change per-request for the same host, pass an `OutboundAuth` instance via Guzzle's `auth` option:

```php
use AffordableMobiles\GServerlessSupportLaravel\Auth\OutboundAuth;
use Illuminate\Support\Facades\Http;

$response = Http::withOptions([
    'auth' => OutboundAuth::password(
        tokenUrl: 'https://auth.example.com/oauth/token',
        clientId: $brandConfig['client_id'],
        clientSecret: $brandConfig['client_secret'],
        username: $brandConfig['username'],
        password: $brandConfig['password'],
    ),
])->post('https://api.example.com/v1/resource', $payload);
```

Or for client credentials:

```php
$response = Http::withOptions([
    'auth' => OutboundAuth::clientCredentials(
        tokenUrl: 'https://auth.example.com/oauth2/token',
        clientId: $partnerId,
        clientSecret: $partnerSecret,
    ),
])->get('https://api.example.com/v1/orders');
```

---

## Exception Hierarchy & SRE Visibility

Authentication failures throw strongly typed exceptions under `AffordableMobiles\GServerlessSupportLaravel\Auth\Exception`:

```
\RuntimeException
 └── OutboundAuthenticationException
      ├── ConfigurationException              // Missing or invalid driver credentials
      ├── SigningException                    // IAM signBlob / KMS sign / OpenSSL failure
      ├── TokenExchangeException              // Provider token endpoint returned 4xx/5xx or invalid payload
      │    └── InvalidClientCredentialsException // Specifically 400 invalid_client / 401 on token endpoint
      └── TargetUnauthorizedException         // Target resource returned 401/403 even after auth injection
```

- **Sensitive Data Redaction**: Passwords and client secrets are automatically omitted from exception messages and stack traces.
- **SRE Integration**: When caught by `HttpClientServiceProvider` / `OutboundHttpTelemetryListener`, exceptions report to Google Cloud Error Reporting with clean, host-isolated signatures:
  `[auth.example.com] POST /oauth/v2/token -> 401 Unauthorized`
