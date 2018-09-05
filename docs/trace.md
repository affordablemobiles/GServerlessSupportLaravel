# StackDriver Trace Integration

This package includes built in support for tracing important components to StackDriver Trace.

By default, this includes:
* Laravel startup
  * (currently doesn't render in the tree view properly)
* Laravel internals (including more granular startup).
  * Application construct
  * Request capture
  * Request handle
  * Response send
  * Request terminate (cleanup)
* Application specific
  * (soon) Middleware
  * Time in Router
  * Controller / Route-Closure Runtime
  * Blade view compile/render
* External calls / RPC
  * memcached
  * (soon) redis
  * MySQL
  * PDO
  * Eloquent (Laravel)
  * (soon) Datastore
  * Guzzle (HTTP(s))

It also allows you to register your own trace providers to be registered as the application boots, via the config file for this package (`trace_providers` in `gaesupport.php`).

## Architecture
There are two different levels of trace integration:

* Low Level (to catch Laravel core boot)
* Higher level (via Service Provider)

All of the trace providers at all levels should implement the interface `OpenCensus\Trace\Integrations\IntegrationInterface`, which providers a static `load` method where the trace hooks are registered.

### Low level
To allow us to capture the startup of Laravel in more detail and to make sure the core is ready to be traced before any of it runs, we set up the OpenCensus trace library and register some trace hooks before loading Laravel.

We do this by asking composer to include `src/preload.php` once it has set up the autoloader, the same way helper functions are initialised.

`src/preload.php` will first include `src/helpers.php` to register our helper functions (which is done because composer can't guarantee load order if we specify an array of files to load in `composer.json` and we need our helper functions before the preload functions run).

By default, the list of low level providers is provided by calling `A1comms\GaeSupportLaravel\Trace\LowLevelLoader`, but this can be overridden by creating your own `LowLevelLoader` that implements `A1comms\GaeSupportLaravel\Trace\LowLevelLoaderInterface` at `App\Trace\LowLevelLoader`, which we check for before loading the default.

Example `app/Trace/LowLevelLoader.php` that just loads `Memcached` tracing:

```php
<?php

namespace App\Trace;

use A1comms\GaeSupportLaravel\Trace\LowLevelLoaderInterface;

class LowLevelLoader implements LowLevelLoaderInterface
{
    public static function getList()
    {
        return [
            OpenCensus\Trace\Integrations\Memcached::class,
        ];
    }
}
```

### Higher Level
For trace providers that can be registered after Laravel has loaded and during the service provider boot, when other service providers may already be running, we can register them via the `TraceServiceProvider` and have Laravel functionality available if required.

Firstly, the `TraceServiceProvider` will register an event for `laravel/boostrap`, with a start time set to `LARAVEL_START` as set in the top of `public/index.php`, to document the point in execution when it was run, as an indication of when Laravel finished loading.

Next, it will look in the application config for our service (`gaesupport.php`) and run the `load` function for any classes listed in the `trace_providers` array, allowing you to add trace hooks for your own application.

As an example, to trace the `enqueue` function of a model class `CustomQueue`,  I'd start by creating the file `app/Trace/CustomQueueModel.php` with the contents:

```php
<?php

namespace App\Trace;

use OpenCensus\Trace\Integrations\IntegrationInterface;
use App\CustomQueue;

class CustomQueueModel implements IntegrationInterface
{
    public static function load()
    {
        if (!extension_loaded('opencensus')) {
            trigger_error('opencensus extension required to load Laravel integrations.', E_USER_WARNING);
            return;
        }

        opencensus_trace_method(CustomQueue::class, 'enqueue', [self::class, 'handleEnqueue']);
    }

    public static function handleResponseSend($scope, $job)
    {
        return [
            'name' => 'model/CustomQueue/enqueue',
            'attributes' => [
              'job' => $job,
            ]
        ];
    }
}
```

To note here is the importance of the parameters passed to the callback function, with the first being the instance of the class as it was called, then all of the parameters to the function as it was called.

For more information, see the OpenCensus PECL extension documentation.

https://github.com/census-instrumentation/opencensus-php/blob/master/docs/content/using-the-extension.md

Next, update your `gaesupport.php` configuration file to include that trace provider into the array:

```php
    'trace_providers' => [
        App\Trace\CustomQueueModel:class,
    ],
```

## Installation
Since the low level trace setup is done as part of the composer autoloader initialisation, most of the installation is taken care of once you've installed the package, although for the higher level & custom trace providers, you'll need to make sure the `GaeSupportServiceProvider` and `TraceServiceProvider` are both loaded into Laravel.

In Laravel 5.5, service providers are automatically discovered by default, so unless you've disabled this functionality, you shouldn't need to do anything else either.

## Guzzle Sub-Request Trace Merging
StackDriver Trace has the ability to show trace points from sub-requests within the same project (although it can be to other App Engine services) into the same trace entry in the GUI, allowing you to view the aggregate impact of a whole request in a micro-service environment.

To take advantage of this, replace your `GuzzleHttp\Client` with `A1comms\GaeSupportLaravel\Integration\Guzzle\Client`.