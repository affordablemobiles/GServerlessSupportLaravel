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

### Laravel Specific (Not Lumen)

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

**6.** In `config/logging.php`, configure a custom logger and set it as the default:

*It's also useful to set the emergency log path to a location App Engine will forward to Stackdriver Logging, see below.*

```php
<?php

use AffordableMobiles\GServerlessSupportLaravel\Log\CreateLoggingDriver;

return [
    
    'default' => 'gae',

    'channels' => [
        'gae' => [
            'driver' => 'custom',
            'via' => CreateLoggingDriver::class,
        ],

        'emergency' => [
            'path' => '/var/log/emergency.log',
        ],
    ],

];
```

**7.** In `.env`, set the following:

```
QUEUE_CONNECTION=gae
CACHE_DRIVER=array
SESSION_DRIVER=gae
LOG_CHANNEL=gae
```

### Lumen Specific (Not Laravel)

**1.** Update `bootstrap/app.php` to load the overridden application class

```php
/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| Here we will load the environment and create the application instance
| that serves as the central piece of this framework. We'll use this
| application as an "IoC" container and router for this framework.
|
*/

$app = new AffordableMobiles\GServerlessSupportLaravel\Foundation\LumenApplication(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);
```

**2.** Update `app/Exceptions/Handler.php` to enable proper Exception logging to StackDriver Error Reporting & Logging:

Change the following `use` statement:

```php
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
```

To our class, that'll inject the required logging hook:

```php
use AffordableMobiles\GServerlessSupportLaravel\Foundation\Exceptions\LumenHandler as ExceptionHandler;
```

## Upgrading (from Laravel/Lumen 9.x LTS)

### Laravel Specific (Not Lumen)

**1.** Update the package version in `composer.json`:

```json
"require": {
    "affordablemobiles/g-serverless-support-laravel": "~11"
}
```

**2.** Follow the Laravel upgrade steps for all versions 6.x ... 9.x

### Lumen Specific (Not Laravel)

**1.** Update the package version in `composer.json`:

```json
"require": {
    "affordablemobiles/g-serverless-support-laravel": "~11"
}
```

**2.** Follow the Lumen upgrade steps for all versions 6.x ... 9.x
