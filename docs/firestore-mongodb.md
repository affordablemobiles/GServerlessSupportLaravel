# Firestore (MongoDB Compatibility Mode) Integration

GCP's [Firestore in MongoDB compatibility mode](https://cloud.google.com/firestore/docs/apis-and-libraries#mongodb_compatibility) allows you to use the MongoDB wire protocol to interact with Firestore, via a proxy that translates MongoDB operations into Firestore operations.

Authentication to the Firestore MongoDB proxy uses IAM credentials, but the PHP `mongodb` PECL driver versions available in App Engine / Cloud Run runtimes don't yet support the `MONGODB-OIDC` authentication mechanism natively. To work around this, we use the same IAM credential resolution approach as our [Cloud SQL integration](cloud-sql.md), passing the OAuth2 access token via standard MongoDB authentication (SCRAM).

## Prerequisites

This integration requires the [`mongodb/laravel-mongodb`](https://github.com/mongodb/laravel-mongodb) package. Install it with:

```bash
composer require mongodb/laravel-mongodb
```

The integration is **optional**: if `mongodb/laravel-mongodb` is not installed, the [DatabaseServiceProvider](../src/AffordableMobiles/GServerlessSupportLaravel/Database/DatabaseServiceProvider.php) will skip MongoDB-related registration entirely, guarded by a `class_exists()` check, so there are no fatal errors if the package is absent.

## How It Works

### The OIDC Workaround

The official GCP documentation refers to this as "Connecting with a temporary access token." Because the `MONGODB-OIDC` mechanism isn't available in the current PHP driver, you bypass it entirely by falling back to standard MongoDB authentication (SCRAM), where the Firestore proxy intercepts a very specific set of credentials:

* **Username**: Must be the literal string `access_token`.
* **Password**: The actual GCP IAM OAuth2 access token (obtained from the metadata server).
* **Auth Mechanism**: Standard MongoDB authentication — you leave the `authMechanism` parameter out of your DSN/options entirely.

### Closure-Based Credentials

Just as with our [Cloud SQL IAM authentication](cloud-sql.md#iam-authentication), the password value in the database configuration must be dynamic: the OAuth2 access token has a limited lifetime and must be refreshed.

By default, `mongodb/laravel-mongodb` evaluates connection configuration once during `Connection` construction — Closures in `username` or `password` are not resolved. Our [custom Connection class](../src/AffordableMobiles/GServerlessSupportLaravel/Database/MongoDB/Connection.php) extends `MongoDB\Laravel\Connection` and overrides `createConnection()` to resolve any Closure values for `username` and `password` before passing them to the MongoDB `Client`:

```php
protected function createConnection(string $dsn, array $config, array $options): Client
{
    if (\array_key_exists('username', $config)) {
        $config['username'] = value($config['username']);
    }

    if (\array_key_exists('password', $config)) {
        $config['password'] = value($config['password']);
    }

    return parent::createConnection($dsn, $config, $options);
}
```

This mirrors the pattern used in our [ConnectionFactory](../src/AffordableMobiles/GServerlessSupportLaravel/Database/Connectors/ConnectionFactory.php) for Cloud SQL, where `resolveRuntimeConfig()` calls `value()` on the password at connection time, ensuring fresh credentials on every connection attempt.

## Setup

### 1. Enable the DatabaseServiceProvider

If you haven't already (e.g. for Cloud SQL), enable the `DatabaseServiceProvider` by updating `config/app.php`:

```php
'providers' => ServiceProvider::defaultProviders()->merge([
    ...
    \AffordableMobiles\GServerlessSupportLaravel\Database\DatabaseServiceProvider::class,
    ...
])->replace([
    ...
])->toArray(),
```

The MongoDB integration is registered automatically within this provider when `mongodb/laravel-mongodb` is detected — no additional service provider is needed.

### 2. Configure the MongoDB Connection

In `config/database.php`, add a `mongodb` connection using the [IAMAuthentication](../src/AffordableMobiles/GServerlessSupportLaravel/Database/Auth/IAMAuthentication.php) singleton for the password:

```php
use \AffordableMobiles\GServerlessSupportLaravel\Database\Auth\IAMAuthentication;
...

return [
    ...
    'connections' => [
        ...

        'mongodb' => [
            'driver'   => 'mongodb',
            'host'     => env('MONGODB_HOST'), // e.g., <UID>.<LOCATION>.firestore.goog
            'port'     => env('MONGODB_PORT', 443),
            'database' => env('MONGODB_DATABASE'),
            'username' => 'access_token',
            'password' => static fn () => app(IAMAuthentication::class)->password(),
            'options'  => [
                'tls'               => true,
                'loadBalanced'      => true,
                'retryWrites'       => false,
                'authMechanism'     => 'PLAIN',
            ],
        ],

        ...
    ],
    ...
];
```

The `username` is the literal string `access_token` as required by the Firestore MongoDB proxy. The `password` is a Closure that fetches a fresh OAuth2 access token from the GCP metadata server via the existing `IAMAuthentication` class.

## Provider Loading Order

Both `mongodb/laravel-mongodb` and our `DatabaseServiceProvider` register a `mongodb` driver extension using `$db->extend('mongodb', ...)`. Laravel's `DatabaseManager::extend()` simply overwrites the resolver for a given driver name, so **the last registration wins**.

The loading order is determined by Laravel's `registerConfiguredProviders()` method, which orders providers as follows:

1. `Illuminate\*` framework providers (from `defaultProviders()`)
2. **Auto-discovered providers** (from composer `extra.laravel.providers`) — this includes `MongoDBServiceProvider`
3. **Non-Illuminate providers from `config/app.php`** (from `merge([...])`) — this includes our `DatabaseServiceProvider`
4. Providers from `bootstrap/providers.php`

Since `MongoDBServiceProvider` is auto-discovered (step 2) and `DatabaseServiceProvider` is listed in `config/app.php` (step 3), our provider always registers **after** the MongoDB package's provider. Both use `$app->resolving('db', ...)`, and the resolving callbacks fire in registration order when the `db` service is first resolved. Our `extend('mongodb', ...)` call overwrites the one from `MongoDBServiceProvider`, substituting our custom `Connection` class.

**This means no special configuration is needed** — you do not need to disable auto-discovery for `mongodb/laravel-mongodb`, and you do not need to worry about provider ordering in `config/app.php`. It works correctly out of the box.

### If You Need to Verify

You can confirm the correct connection class is being used by checking the class of a resolved MongoDB connection:

```php
$connection = DB::connection('mongodb');
get_class($connection);
// Should be: AffordableMobiles\GServerlessSupportLaravel\Database\MongoDB\Connection
```
