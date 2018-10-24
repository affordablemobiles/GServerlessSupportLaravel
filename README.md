# GaeSupportLaravel

Google App Engine (GAE) Standard and Flexible Environment support package for Laravel 5.1 (on PHP 5.5 runtime).

[![Latest Stable Version](https://poser.pugx.org/a1comms/gae-support-laravel/v/stable)](https://packagist.org/packages/a1comms/gae-support-laravel)
[![Monthly Downloads](https://poser.pugx.org/a1comms/gae-support-laravel/d/monthly)](https://packagist.org/packages/a1comms/gae-support-laravel)
[![Total Downloads](https://poser.pugx.org/a1comms/gae-support-laravel/downloads)](https://packagist.org/packages/a1comms/gae-support-laravel)
[![Latest Unstable Version](https://poser.pugx.org/a1comms/gae-support-laravel/v/unstable)](https://packagist.org/packages/a1comms/gae-support-laravel)
[![License](https://poser.pugx.org/a1comms/gae-support-laravel/license)](https://packagist.org/packages/a1comms/gae-support-laravel)

Based on original work for App Engine Standard by @shpasser https://github.com/shpasser/GaeSupportL5

This version provides compatibility with PHP 5.5 and Laravel 5.1, while having the same interface as the PHP 7.2 / Laravel 5.5 version, to allow for easy migration.

## Functionality
* StackDriver Logging integration
* StackDriver Trace integration (**not on PHP 5.5**) (see [docs/trace.md](https://github.com/a1comms/GaeSupportLaravel/blob/php72-laravel55/docs/trace.md))
* Blade View Pre-Compiler (optional, see [docs/blade-pre-compile.md](https://github.com/a1comms/GaeSupportLaravel/blob/php72-laravel55/docs/blade-pre-compile.md))
* Guzzle integration (optional, see [docs/guzzle.md](https://github.com/a1comms/GaeSupportLaravel/blob/php72-laravel55/docs/guzzle.md))

## Installation

Pull in the package via Composer.

```js
"require": {
    "a1comms/gae-support-laravel": "~5.4"
}
```

For Laravel, modify `bootstrap/app.php` and change this:

```php
$app = new Illuminate\Foundation\Application(
    realpath(__DIR__.'/../')
);
```

To this:

```php
$app = new A1comms\GaeSupportLaravel\Foundation\Application(
    realpath(__DIR__.'/../')
);

/*
|--------------------------------------------------------------------------
| Setup Early Logging
|--------------------------------------------------------------------------
*/
A1comms\GaeSupportLaravel\Log\Logger::setup($app);
```

Then include these service providers within `config/app.php`:

```php
'providers' => [
    A1comms\GaeSupportLaravel\GaeSupportServiceProvider::class,
    A1comms\GaeSupportLaravel\View\ViewServiceProvider::class,
    A1comms\GaeSupportLaravel\Queue\QueueServiceProvider::class,
    A1comms\GaeSupportLaravel\Trace\TraceServiceProvider::class,
];
```

`A1comms\GaeSupportLaravel\Queue\QueueServiceProvider::class` should replace `Illuminate\Queue\QueueServiceProvider::class` in that array.

`A1comms\GaeSupportLaravel\View\ViewServiceProvider::class` provides blade view pre-complication, so we no longer have to rely on cachefs as in the previous version.

`A1comms\GaeSupportLaravel\Trace\TraceServiceProvider::class` provides automatic tracing to Stackdriver Trace in the PHP 7.2 version, but is mainly a placeholder in this version.

To prepare for deployment to App Engine, which includes pre-compiling the blade templates, run this command:

`php artisan gae:prepare`

We recommend adding this to `composer.json` as:

```json
    "scripts": {
        "post-autoload-dump": [
            "php artisan gae:prepare"
        ]
    },
```

This is the recommended method for the new PHP 7.2 runtime builder (although you need to use `--no-cache` on `gcloud app deploy`)