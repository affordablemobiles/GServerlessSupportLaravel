<?php

declare(strict_types=1);

if (!function_exists('is_cloud_run')) {
    function is_cloud_run()
    {
        return isset($_SERVER['K_SERVICE']);
    }
}

if (!function_exists('is_gae')) {
    function is_gae()
    {
        // Cloud Run emulates App Engine
        return is_cloud_run() || isset($_SERVER['GAE_INSTANCE']);
    }
}

if (!function_exists('is_gae_std')) {
    function is_gae_std()
    {
        if (isset($_SERVER['GAE_ENV'])) {
            if ('standard' === $_SERVER['GAE_ENV']) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('is_gae_std_legacy')) {
    function is_gae_std_legacy()
    {
        return false;
    }
}

if (!function_exists('is_gae_production')) {
    function is_gae_production()
    {
        return is_gae();
    }
}

if (!function_exists('is_gae_development')) {
    function is_gae_development()
    {
        if (is_cloud_run()) {
            return (bool) (config('gaesupport.dev-prefix')
                && str_starts_with($_SERVER['HTTP_HOST'], config('gaesupport.dev-prefix')));
        }

        return (bool) (config('gaesupport.dev-prefix')
            && str_starts_with(gae_version(), config('gaesupport.dev-prefix')));
    }
}

if (!function_exists('is_gae_flex')) {
    function is_gae_flex()
    {
        if (isset($_SERVER['GOOGLE_CLOUD_PROJECT'])) {
            if (!isset($_SERVER['GAE_ENV'])) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('gae_instance')) {
    function gae_instance()
    {
        if (is_cloud_run()) {
            // there is no instance idenfitier on Cloud Run
            // return the revision so we aren't returning nothing.
            return $_SERVER['K_REVISION'];
        }
        if (is_gae()) {
            return $_SERVER['GAE_INSTANCE'];
        }

        return false;
    }
}

if (!function_exists('gae_project')) {
    function gae_project()
    {
        if (is_gae()) {
            if (isset($_SERVER['GOOGLE_CLOUD_PROJECT'])) {
                return $_SERVER['GOOGLE_CLOUD_PROJECT'];
            }

            return (new \Google\Cloud\Core\Compute\Metadata())->getProjectId();
        }

        return false;
    }
}

if (!function_exists('gae_service')) {
    function gae_service()
    {
        if (is_cloud_run()) {
            return $_SERVER['K_SERVICE'];
        }
        if (is_gae()) {
            return $_SERVER['GAE_SERVICE'];
        }

        return false;
    }
}

if (!function_exists('gae_version')) {
    function gae_version()
    {
        if (is_cloud_run()) {
            return $_SERVER['K_REVISION'];
        }
        if (is_gae()) {
            return $_SERVER['GAE_VERSION'];
        }

        return false;
    }
}

if (!function_exists('gae_storage_path')) {
    function gae_storage_path($path = '')
    {
        if (is_gae() || defined('IS_GAE')) {
            $ret = '/tmp/laravel/storage'.($path ? DIRECTORY_SEPARATOR.$path : $path);
            if (is_gae_development()) {
                $ret = '/tmp/laravel/'.$_SERVER['HTTP_HOST'].'/storage'.($path ? DIRECTORY_SEPARATOR.$path : $path);
            }
            @mkdir($ret, 0755, true);

            return $ret;
        }

        return storage_path($path);
    }
}

if (!function_exists('gae_realpath')) {
    function gae_realpath($path)
    {
        $result = realpath($path);
        if (false === $result) {
            if (file_exists($path)) {
                $result = $path;
            }
        }

        return $result;
    }
}

if (!function_exists('app_path')) {
    function app_path($path = '')
    {
        $extra = empty($path) ? '' : ('/'.$path);

        return base_path('app').$extra;
    }
}

if (!function_exists('public_path')) {
    /**
     * Get the path to the public folder.
     *
     * @param string $path
     *
     * @return string
     */
    function public_path($path = null)
    {
        return rtrim(app()->basePath('public/'.$path), '/');
    }
}

if (!function_exists('is_lumen')) {
    function is_lumen()
    {
        return class_exists('\Laravel\Lumen\Application');
    }
}

if (!function_exists('gae_basic_log')) {
    function gae_basic_log($logName, $severity, $message, $context = []): void
    {
        $record = [
            'severity'                     => $severity,
            'message'                      => $message,
            'context'                      => $context,
            'customLogName'                => $logName,
            'logging.googleapis.com/trace' => 'projects/'.gae_project().'/traces/'.\OpenCensus\Trace\Tracer::spanContext()->traceId(),
            'time'                         => (new DateTimeImmutable())->format(DateTimeInterface::RFC3339_EXTENDED),
        ];

        if (is_cloud_run()) {
            @file_put_contents('/tmp/logpipe', json_encode($record)."\n", FILE_APPEND);
        } else {
            @file_put_contents('/var/log/'.$logName.'.log', json_encode($record)."\n", FILE_APPEND);
        }
    }
}

if (!function_exists('diefast')) {
    function diefast($data = null): void
    {
        register_shutdown_function(function (): void {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
        });

        exit($data);
    }
}
