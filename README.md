# GaeSupportLaravel

Google App Engine (GAE) Standard and Flexible Environment support package for Laravel 5.1.

[![Latest Stable Version](https://poser.pugx.org/a1comms/gae-support-laravel/v/stable)](https://packagist.org/packages/a1comms/gae-support-laravel)
[![Monthly Downloads](https://poser.pugx.org/a1comms/gae-support-laravel/d/monthly)](https://packagist.org/packages/a1comms/gae-support-laravel)
[![Total Downloads](https://poser.pugx.org/a1comms/gae-support-laravel/downloads)](https://packagist.org/packages/a1comms/gae-support-laravel)
[![Latest Unstable Version](https://poser.pugx.org/a1comms/gae-support-laravel/v/unstable)](https://packagist.org/packages/a1comms/gae-support-laravel)
[![License](https://poser.pugx.org/a1comms/gae-support-laravel/license)](https://packagist.org/packages/a1comms/gae-support-laravel)

Based on original work for App Engine Standard by @shpasser https://github.com/shpasser/GaeSupportL5

This library is designed for homogeneous operation between the Standard Environment and the Flexible Environment, with the only change required being a different prepare command before deployment.

Currently supported features:
 * File system compatibility (write files to `cachefs` or `/tmp/laravel/storage`).
 * Automatically cache and patch configuration files.
 * Datastore persistent session handler.
 * Full microservice support in the session handler, by including `gae_service()` in the storage key.
 * Full microservice and version support in `cachefs`, so versions and services in the Standard Environment don't conflict.

## Installation

Pull in the package via Composer.

```js
 "require": {
     "a1comms/gae-support-laravel": "~5.1"
 }
```

Then include the service provider within `config/app.php`.

```php
 'providers' => [
     A1comms\GaeSupportLaravel\GaeSupportServiceProvider::class
 ];
```

To automatically patch your configuration files ready for use with GAE, run the setup command:

```bash
 php artisan gae:setup
```

## Deployment & Local Development
Before deployment, you'll need to run the prepare command which will prepare for running in the live environment.

This will ask Laravel to generate a config/route cache so it doesn't need to generate it on live while the file system is read-only, plus it'll patch some additional values in this cache file, since there is more exposed here than can be changed in the raw config files.

```bash
php artisan gae:prepare env
```

Replace `env` with the environment you'll be running on, std (Standard) or flex (Flexible).

This will also manage your `.env` files for you, moving `.env` to `.env.local` and `.env.production` to `.env` (if `.env.production` exists).

To revert back to a local development state, you can run the local development prepare command.

```bash
php artisan gae:prepare local
```

This will move `.env` to `.env.production` and `.env.local` to `.env` (if `.env.local` exists) and also re-generate the config cache to revert any patches/modifications required to run on the live environment.

## Automatic changes when GAE is detected.
When GAE Standard is detected at runtime, a few changes are automatically applied:
 * The syslog handler is loaded and forced to be the default monolog handler.
 * The storage directory is re-written to `cachefs://` which is an emulated file-system on top of memcache.

When GAE Flexible is detected at runtime, these changes are automatically applied:
 * The structured log handler is loaded and forced to be the default monolog handler.
 * The `gs://` stream wrapper is loaded with the default credentials from the underlying GCE instance.
 * The storage directory is changed to `/tmp/laravel/storage`

## Temporary Storage
By default, we set the temporary storage directory to `cachefs://` on Standard and `/tmp/laravel/storage` on Flexible.

This will store things like the framework cache files (services.json, routes.php & config.php), as well as the compiled views.

The storage on Flexible will be unique per instance and ephemeral, so it isn't suitable for storing caches of application data, e.g. from a DB.

Since deploying new code or config would mean deploying a new version, we can safely cache the config & views here.

The storage on the Standard Environment is shared between all instances, but can clear at any time, so we've included a few modifications to prevent issues.

The main one is an altered Blade compiler, with an `isExpired` function that reads the whole view cache rather than checking if the file exists, which in turn causes the modified `cachefs` driver to create a local in-memory cache of the contents using variables, so it isn't affected if memcache is cleared between the `isExpired` check and the actual view rendering, which we used to see causing quite a few fatal errors.

## Persistent Sessions in DataStore
We've included a session driver using DataStore for persistence, cached by memcache* for faster access times and reduced billing, giving you the best of both worlds when it comes to speed and persistence.

To make use of this, set:
- `SESSION_DRIVER=gae` in `.env`

(*) memcache code is included, but since it isn't currently available on the Flexible environment, this won't function on there for now.

## Helper Functions
There are a set of helper functions included that you can use for GAE specific purposes:
 * `gae_project()`
   * The Google Cloud project ID.
 * `gae_service()`
   * The App Engine Service/Module name for the current instance.
 * `gae_version()`
   * The App Engine version name for the current instance.
 * `gae_instance()`
   * The App Engine instance name.
 * `is_gae()`
   * Are we running on App Engine, returns true for Standard Environment + Local SDK & Flexible Environment.
 * `is_gae_std()`
   * Are we running on App Engine Standard Environment, also returns true for the Local SDK.
 * `is_gae_flex()`
   * Are we running on App Engine Flexible Environment.
