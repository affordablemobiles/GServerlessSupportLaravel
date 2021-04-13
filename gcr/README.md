# Cloud Run

In this documentation, we discuss running Laravel (with GaeSupportLaravel) on [fully managed Cloud Run](https://cloud.google.com/run/docs/reference/container-contract), using [Google's buildpacks from the App Engine PHP runtimes](https://console.cloud.google.com/gcr/images/gae-runtimes/EU/buildpacks/php74/builder).

## Supported Features

- [x] Structured Logging in Cloud Logging
- [x] Log attribution via trace id
- [x] Application metrics via Cloud Trace
- [x] Guzzle Integration
- [x] Auth & IAP Integration
- [ ] Cloud Tasks

## Cloud Build Example

WIP

## Runtime differences

### Logging

Unlike App Engine, where we write structured logs to files in `/var/log`, Cloud Run behaves more like Kubernetes, where it expects structured logs to be written to stdout (in the absence of an async client for the Logs API, as in PHP's case).

Writing to stdout directly with:
```php
file_put_contents('php://stdout', json_encode([
    'jsonPayload' => [
        'message' => 'test',
    ],
]));
```
makes it into the logs, but it isn't parsed, as php-fpm adds a prefix, making it invalid JSON.

To get around this, we'll make use of a [custom entrypoint](entrypoint.sh).

This creates a FIFO pipe that we can write to from PHP, while keeping a background process alive that forwards data from the pipe directly to stdout, resulting in structured logs that get parsed with all expected metadata.

**When building with the buildpack, specify the environment variable**:

```
GOOGLE_ENTRYPOINT=/workspace/vendor/a1comms/gae-support-laravel/gcr/entrypoint.sh
```

### Static Assets

Previously with App Engine, you could specify folders & files of static assets in `app.yaml` that would be uploaded & versioned alongside your app, but served independantly of your application's instances.

This isn't possible with Cloud Run, you either have to self-manage & version your assets in Google Cloud Storage, or let your instances serve those assets from the application container.

While the idiomatic method for this in a PHP environment would be to let nginx handle it, this isn't currently supported with Google's buildpacks.

Until a better solution is presented, we emulate how this would work in a Go app, by letting our Laravel application serve the static assets from the public folder:

**To enable the workaround**, add the following to `config/app.php`:

```php
    'providers' => [
        ...
        App\Providers\RouteServiceProvider::class,
        ...

        // Static files for Google Cloud Run
        A1comms\GaeSupportLaravel\Filesystem\StaticFilesServiceProvider::class,
    ],
```

As shown, ensure it sits below the default `RouteServiceProvider`.