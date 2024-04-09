# GServerlessSupportLaravel

Google Serverless (Cloud Run & App Engine Standard Environment) support package for **Laravel 11.x**.

[![Latest Stable Version](https://poser.pugx.org/affordablemobiles/g-serverless-support-laravel/v/stable)](https://packagist.org/packages/affordablemobiles/g-serverless-support-laravel)
[![Monthly Downloads](https://poser.pugx.org/affordablemobiles/g-serverless-support-laravel/d/monthly)](https://packagist.org/packages/affordablemobiles/g-serverless-support-laravel)
[![Total Downloads](https://poser.pugx.org/affordablemobiles/g-serverless-support-laravel/downloads)](https://packagist.org/packages/affordablemobiles/g-serverless-support-laravel)
[![License](https://poser.pugx.org/affordablemobiles/g-serverless-support-laravel/license)](https://packagist.org/packages/affordablemobiles/g-serverless-support-laravel)

Based on original work for App Engine Standard (on the PHP5.5 runtime) by @shpasser https://github.com/shpasser/GaeSupportL5

## Functionality
* Google Cloud Operations Suite integration
    * Cloud Logging destination with structured logs.
    * Cloud Trace (via [opentelemetry](https://github.com/open-telemetry/opentelemetry-php)) (see [docs/trace.md](https://github.com/affordablemobiles/GServerlessSupportLaravel/blob/php8.3-laravel11.x/docs/trace.md))
        * Guzzle propagation support (optional, see [docs/trace.md](https://github.com/affordablemobiles/GServerlessSupportLaravel/blob/php8.3-laravel11.x/docs/trace.md#guzzle))
* Identity Aware Proxy (IAP) integration (optional, see [docs/iap-auth-verify.md](https://github.com/affordablemobiles/GServerlessSupportLaravel/blob/php8.3-laravel11.x/docs/iap-auth-verify.md))
* Blade View Pre-Compiler (optional, see [docs/blade-pre-compile.md](https://github.com/affordablemobiles/GServerlessSupportLaravel/blob/php8.3-laravel11.x/docs/blade-pre-compile.md))
* Queue Driver for Cloud Tasks (optional, see [docs/queue.md](https://github.com/affordablemobiles/GServerlessSupportLaravel/blob/php8.3-laravel11.x/docs/queue.md))
* Examples for deployment to App Engine from Git via Cloud Build, plus encrypted secrets with Secret Manager (optional, see [docs/cloudbuild.md](https://github.com/affordablemobiles/GServerlessSupportLaravel/blob/php8.3-laravel11.x/docs/cloudbuild.md))

## Installation

Pull in the package via Composer:

```js
    "require": {
        "affordablemobiles/g-serverless-support-laravel": "~11"
    }
```

**1.** Add the following to `composer.json`:

```json
    "scripts": {
        "post-autoload-dump": [
            "php artisan g-serverless:prepare"
        ]
    },
```

**2.** For Laravel, configure the service providers within `config/app.php` by adding:

```php
    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on any
    | requests to your application. You may add your own services to the
    | arrays below to provide additional features to this application.
    |
    */

    'providers' => \Illuminate\Support\ServiceProvider::defaultProviders()->merge([
        // Package Service Providers...
        AffordableMobiles\GServerlessSupportLaravel\GServerlessSupportServiceProvider::class,
        AffordableMobiles\GServerlessSupportLaravel\Auth\AuthServiceProvider::class,
    ])->replace([
        \Illuminate\View\ViewServiceProvider::class => AffordableMobiles\GServerlessSupportLaravel\View\ViewServiceProvider::class,
    ])->toArray(),
```

**4.** Update `bootstrap/app.php` to load the overridden application class & initialise logging to Stackdriver:

```php
/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| The first thing we will do is create a new Laravel application instance
| which serves as the "glue" for all the components of Laravel, and is
| the IoC container for the system binding all of the various parts.
|
*/

$app = new AffordableMobiles\GServerlessSupportLaravel\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);
```

**5.** Update `bootstrap/app.php` to enable proper exception logging to Error Reporting & Logging:

Change the following `use` statement:

```php
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (Throwable $e) {
            \AffordableMobiles\GServerlessSupportLaravel\Integration\ErrorReporting\Report::exceptionHandler($e);
        })->stop();
    })
```

**7.** In `.env`, set the following:

```
LOG_CHANNEL=stderr
LOG_STDERR_FORMATTER=AffordableMobiles\GServerlessSupportLaravel\Log\JsonFormatter
CACHE_DRIVER=array
SESSION_DRIVER=datastore
```

## Upgrading (from Laravel 9.x LTS)

**1.** Update the package version in `composer.json`:

```json
    "require": {
        "affordablemobiles/g-serverless-support-laravel": "~11"
    }
```

**2.** Follow the Laravel upgrade steps for all versions 6.x ... 9.x
