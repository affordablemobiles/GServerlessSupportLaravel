# GaeFlexSupportL5

Google App Engine (GAE) Flexible Environment support package for Laravel 5.1.

[![Latest Stable Version](https://poser.pugx.org/a1comms/gae-flex-support-l5/v/stable)](https://packagist.org/packages/a1comms/gae-flex-support-l5)
[![Monthly Downloads](https://poser.pugx.org/a1comms/gae-flex-support-l5/d/monthly)](https://packagist.org/packages/a1comms/gae-flex-support-l5)
[![Total Downloads](https://poser.pugx.org/a1comms/gae-flex-support-l5/downloads)](https://packagist.org/packages/a1comms/gae-flex-support-l5)
[![Latest Unstable Version](https://poser.pugx.org/a1comms/gae-flex-support-l5/v/unstable)](https://packagist.org/packages/a1comms/gae-flex-support-l5)
[![License](https://poser.pugx.org/a1comms/gae-flex-support-l5/license)](https://packagist.org/packages/a1comms/gae-flex-support-l5)

Currently supported features:
 * File system compatibility (write files to `/tmp/laravel/storage`).
 * Automatically cache and patch configuration files.
 * DataStore persistent session handler.

 ## Installation

 Pull in the package via Composer.

 ```js
 "require": {
     "a1comms/gae-flex-support-l5": "~5.1"
 }
 ```

 Then include the service provider within `config/app.php`.

 ```php
 'providers' => [
     A1comms\GaeFlexSupportL5\GaeSupportServiceProvider::class
 ];
 ```

## Deployment & Local Development
Before deployment, you'll need to run the config patcher which will prepare for running in the live environment.

```bash
php artisan gae:setup --cache-config
```

This will also manage your `.env` files for you, moving `.env` to `.env.local` and `.env.production` to `.env` (if `.env.production` exists).

To revert back to a local development state, you can run the local development prepare command.

```bash
php artisan gae:setup --local-dev
```

This will move `.env` to `.env.production` and `.env.local` to `.env` (if `.env.local` exists) and also re-generate the config cache without any modifications.

## Automatic changes when GAE is detected.
When GAE is detected at runtime, a few changes are automatically applied:
 * The structured log handler is loaded and forced to be the default monolog handler.
 * The `gs://` stream wrapper is loaded with the default credentials from the underlying GCE instance.
 * The storage directory is changed to `/tmp/laravel/storage`

## Temporary Storage
By default, we set the temporary storage directory to `/tmp/laravel/storage`.

This will store things like the framework cache files (services.json, routes.php & config.php), as well as the compiled views.

This storage will be unique per instance and ephemeral, so it isn't suitable for storing caches of application data, e.g. from a DB.

Since deploying new code or config would mean deploying a new version, we can safely cache the config & views.

### Persistent Sessions in DataStore

We've included a session driver using DataStore for persistence, cached by memcache* for faster access times and reduced billing, giving you the best of both worlds when it comes to speed and persistence.

To make use of this, set:
- `SESSION_DRIVER=gae` in `.env`

(*) memcache code is included, but since it isn't currently available on the Flexible environment, this won't function for now.
