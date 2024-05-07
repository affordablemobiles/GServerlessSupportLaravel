# Guzzle Authentication Middleware

[AuthTokenMiddleware](../src/AffordableMobiles/GServerlessSupportLaravel/Auth/Token/Middleware/AuthTokenMiddleware.php) provides a Guzzle compatible middleware that will inject Google IAP compatible OIDC tokens, or OAuth2 tokens (compatible with Google's own APIs) into outbound requests.

Once enabled, it looks for an "auth" value in the client options:
* `google_oidc` for IAP compatible OIDC tokens.
* `google_oauth2` for Google APIs compatible OAuth2 tokens.
* `google_dynamic` to call a closure to determine the authentication type (see [Dynamic example](guzzle-auth.md#dynamic-example) below).

When using OIDC tokens, either via `google_oidc` or `google_dynamic`, a closure is consulted for the audience value (see examples below).

## Basic example

In this basic example, we are creating client & middleware instances, configured for OIDC authentication using a static audience:

```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Psr\Http\Message\UriInterface;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Token\Middleware\AuthTokenMiddleware;

$audienceSource = static fn (UriInterface $request_uri) => '111111-aaaaaaaaa.apps.googleusercontent.com';

$authMiddleware = new AuthTokenMiddleware($audienceSource);
$stack = HandlerStack::create();
$stack->push($authMiddleware);

$client = new Client([
    'handler' => $stack,
    'auth' => 'google_oidc',
]);

$res = $client->get('https://my-super-secure-app.appspot.com/tasks/list');
```

## Dynamic example

In this more dynamic example, we have a class that defines `AUTH_DOMAIN_MAP`, listing domains & what authentication they require: either `oauth2` for Google API compatible OAuth2 tokens, or the audience value for OIDC authentication (usually the client ID for IAP compatibility):

```php
use A1comms\GaeSupportLaravel\Auth\Token\Middleware\AuthTokenMiddleware;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Psr\Http\Message\UriInterface;

class HttpClient
{
    /**
     * Map of domains requiring authentication.
     *
     * Either "domain" => "oauth2" for "access_token",
     *  or "domain" => "<client_id>/<audience>" for OIDC "id_token".
     */
    public const AUTH_DOMAIN_MAP = [
        'api.example.com'                   => '111111-aaaaaaaaa.apps.googleusercontent.com',
        'example-api.appspot.com'           => '222222-bbbbbbbbb.apps.googleusercontent.com',

        'storage.googleapis.com'            => 'oauth2',
    ];

    private static function getClient(): Client
    {
        $authMap = self::AUTH_DOMAIN_MAP;

        $audienceSource = static fn (UriInterface $request_uri) => $authMap[$request_uri->getHost()];

        $tokenTypeSource = static function (UriInterface $request_uri) use ($authMap) {
            if ($authMap[$request_uri->getHost()] === 'oauth2') {
                return 'oauth2';
            }

            return 'oidc';
        };

        $authMiddleware = new AuthTokenMiddleware($audienceSource, $tokenTypeSource);
        $stack          = HandlerStack::create();
        $stack->setHandler(\GuzzleHttp\choose_handler());
        $stack->push($authMiddleware);

        $clientOptions = [
            'handler'   => $stack,
            'auth'      => 'google_dynamic',
        ];

        return new Client($clientOptions);
    }
}
```