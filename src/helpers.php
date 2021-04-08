<?php

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
        return (is_cloud_run() || isset($_SERVER['GAE_INSTANCE']));
    }
}

if (!function_exists('is_gae_std')) {
    function is_gae_std()
    {
        if (isset($_SERVER['GAE_ENV'])) {
            if ($_SERVER['GAE_ENV'] == "standard") {
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
        return (bool)(config('gaesupport.dev-prefix')
            && strpos(gae_version(), config('gaesupport.dev-prefix')) === 0);
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
        if (is_gae()) {
            return $_SERVER['GAE_INSTANCE'];
        } else {
            return false;
        }
    }
}

if (!function_exists('gae_project')) {
    function gae_project()
    {
        if (is_gae()) {
            return $_SERVER['GOOGLE_CLOUD_PROJECT'];
        } else {
            return false;
        }
    }
}

if (!function_exists('gae_service')) {
    function gae_service()
    {
        if (is_gae()) {
            return $_SERVER['GAE_SERVICE'];
        } else {
            return false;
        }
    }
}

if (!function_exists('gae_version')) {
    function gae_version()
    {
        if (is_gae()) {
            return $_SERVER['GAE_VERSION'];
        } else {
            return false;
        }
    }
}

if (!function_exists('gae_storage_path')) {
    function gae_storage_path($path = '')
    {
        if (is_gae() || defined('IS_GAE')) {
            $ret = '/tmp/laravel/storage'.($path ? DIRECTORY_SEPARATOR.$path : $path);
            @mkdir($ret, 0755, true);
            return $ret;
        } else {
            return storage_path($path);
        }
    }
}

if (!function_exists('gae_realpath')) {
    function gae_realpath($path)
    {
        $result = realpath($path);
        if ($result == false) {
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
        $extra = empty($path) ? '' : ('/' . $path);
        return base_path('app').$extra;
    }
}

if (!function_exists('is_lumen')) {
    function is_lumen()
    {
        return class_exists('\Laravel\Lumen\Application');
    }
}

if (!function_exists('gae_basic_log')) {
    function gae_basic_log($logName = 'app', $severity, $message)
    {
        $record = [
            "severity" => $severity,
            'message' => $message,
            'logging.googleapis.com/trace' => 'projects/'.gae_project().'/traces/'.\OpenCensus\Trace\Tracer::spanContext()->traceId(),
            'time' => (new DateTimeImmutable())->format(DateTimeInterface::RFC3339_EXTENDED),
        ];

        @file_put_contents('/var/log/' .$logName . '.log', json_encode($record) . "\n", FILE_APPEND);
    }
}

if (!function_exists('diefast')) {
    function diefast($data = null)
    {
        register_shutdown_function(function () {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
        });

        die($data);
    }
}
