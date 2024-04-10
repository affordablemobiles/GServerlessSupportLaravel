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

**4.** Update the `use` statement at the top of `bootstrap/app.php` from:

```php
use Illuminate\Foundation\Application;
```

to:

```php
use AffordableMobiles\GServerlessSupportLaravel\Foundation\Application;
```

**5.** Update `bootstrap/app.php` to enable proper exception logging to Error Reporting & Logging:

(want to find a way to remove the need to do this before release, by changes in the overriden `Application` class above).
(also need to ensure that the error reporting class is only called if on a serverless platform).
(should also document in the upgrade notes to switch from using our report class directly, to using Laravel's new `report($e)` helper now it is available).

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

**2.** Follow the Laravel upgrade steps for all versions 9.x ... 11.x

**3.** TODO...