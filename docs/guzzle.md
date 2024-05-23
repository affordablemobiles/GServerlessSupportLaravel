# Guzzle Integration

This package provides the following Guzzle middleware:

* [GuzzleRetryMiddleware](guzzle.md#guzzleretrymiddleware)
* [AuthTokenMiddleware](guzzle.md#authtokenmiddleware)

### Custom Handler Stack

To provide simple configuration, you can use our custom handler stack, that will include both of those middleware with sane defaults:

```php
use GuzzleHttp\Client;
use AffordableMobiles\GServerlessSupportLaravel\Integration\Guzzle\HandlerStack;
use AffordableMobiles\GServerlessSupportLaravel\Integration\Guzzle\GuzzleRetryMiddleware;

$stack  = HandlerStack::create();
$client = new Client([
    'handler' => $stack,
]);
```

### Middleware Injection via OpenTelemetry

For situations where you want to enable this middleware without making code changes (beyond including the authentication map array in your `config` directory), it is possible to automatically inject the middleware into any usage of Guzzle, via OpenTelemetry's [hook](../src/AffordableMobiles/GServerlessSupportLaravel/Trace/Instrumentation/Guzzle/GuzzleInstrumentation.php#L80).

To enable this:

* Specify the environment variable `INJECT_GUZZLE_MIDDLEWARE=true`
* Ensure `extension=opentelemetry.so` is in `php.ini`
* Ensure you are running on App Engine or Cloud Run (the trace hooks aren't active otherwise).

## GuzzleRetryMiddleware

[GuzzleRetryMiddleware](../src/AffordableMobiles/GServerlessSupportLaravel/Integration/Guzzle/GuzzleRetryMiddleware.php) will automatically retry requests (using Google's [Exponential Backoff algorithm](https://github.com/googleapis/google-cloud-php/blob/main/Core/src/ExponentialBackoff.php#L154)) that fail due to network layer failures, i.e. TCP handshake timeout, which is more commonly seen in serverless deployments than on traditional virtual machine infrastructure.

### Example Usage

```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use AffordableMobiles\GServerlessSupportLaravel\Integration\Guzzle\GuzzleRetryMiddleware;

$stack = HandlerStack::create();
$stack->push(GuzzleRetryMiddleware::factory(), 'retry');

$client = new Client([
    'handler' => $stack,
    'max_retry_attempts' => 2, // default 4 if unspecified
]);

$res = $client->get('https://example-api.appspot.com/tasks/list');

// or to disable retries for a specific request...
$res = $client->get('https://example-api.appspot.com/tasks/list', [
    'retry_enabled' => false, // default true
]);
```

## AuthTokenMiddleware

[AuthTokenMiddleware](../src/AffordableMobiles/GServerlessSupportLaravel/Auth/Token/Middleware/AuthTokenMiddleware.php) provides a Guzzle compatible middleware that will inject Google IAP compatible OIDC tokens, or OAuth2 tokens (compatible with Google's own APIs) into outbound requests.

Once enabled, it looks for an "auth" value in the client options:
* `google_oidc` for IAP compatible OIDC tokens.
* `google_oauth2` for Google APIs compatible OAuth2 tokens.
* `google_dynamic` (default if unspecified) to call a closure to determine the authentication type (see [Dynamic example](guzzle-auth.md#dynamic-example) below).

When using OIDC tokens, either via `google_oidc` or `google_dynamic`, a closure is consulted for the audience value (see examples below).

### Example Usage

By default, when using `AuthTokenMiddleware::factory()`, the middleware is configured for you automatically based on finding an audience mapping array (via Laravel's `config(...)` system, with `gserverlesssupport.auth.middleware.audience_map_location` containing the `config(...)` path to the actual mapping array itself), and will inject authentication into any requests that match entries in that domain mapping array.

In `config/gserverlesssupport.php`, define the Laravel `config(...)` path to use for finding the audience mapping array, e.g.:
```php
return [
    'auth' => [
        'middleware' => [
            'audience_map_location' => 'auth.middleware.audience',
        ],
    ],
];
```

And following the above configured path, an example of the audience mapping array in `config/auth.php`:
```php
return [
    ...

    'middleware' => [
        'audience' => [
            'api.example.com'                   => '111111-aaaaaaaaa.apps.googleusercontent.com',
            'example-api.appspot.com'           => '222222-bbbbbbbbb.apps.googleusercontent.com',

            'storage.googleapis.com'            => 'oauth2',
        ],
    ],
];
```

Then when used with Guzzle, if the request destination domain matches something in the audience mapping array, authentication will automatically be injected into the outbound request, e.g.:

```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Token\Middleware\AuthTokenMiddleware;

$stack = HandlerStack::create();
$stack->push(AuthTokenMiddleware::factory(), 'auth_token');

$client = new Client([
    'handler' => $stack,
]);

$res = $client->get('https://example-api.appspot.com/tasks/list');
```