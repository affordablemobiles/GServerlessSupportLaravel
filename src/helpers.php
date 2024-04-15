<?php

declare(strict_types=1);
use AffordableMobiles\GServerlessSupportLaravel\Trace\Propagator\CloudTraceFormatter;
use Google\Cloud\Core\Compute\Metadata;
use OpenTelemetry\API\Trace\SpanContext;

if (!function_exists('is_cloud_run')) {
    function is_cloud_run()
    {
        return isset($_SERVER['K_SERVICE']);
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

if (!function_exists('is_g_serverless')) {
    function is_g_serverless()
    {
        return is_cloud_run() || is_gae_std();
    }
}

if (!function_exists('g_project')) {
    function g_project()
    {
        if (is_g_serverless()) {
            if (isset($_SERVER['GOOGLE_CLOUD_PROJECT'])) {
                return $_SERVER['GOOGLE_CLOUD_PROJECT'];
            }

            return once(static fn () => (new Metadata())->getProjectId());
        }

        return false;
    }
}

if (!function_exists('g_service')) {
    function g_service()
    {
        if (is_cloud_run()) {
            return $_SERVER['K_SERVICE'];
        }
        if (is_gae_std()) {
            return $_SERVER['GAE_SERVICE'];
        }

        return false;
    }
}

if (!function_exists('g_version')) {
    function g_version()
    {
        if (is_cloud_run()) {
            return $_SERVER['K_REVISION'];
        }
        if (is_gae_std()) {
            return $_SERVER['GAE_VERSION'];
        }

        return false;
    }
}

if (!function_exists('g_instance')) {
    function g_instance()
    {
        if (is_gae_std()) {
            return $_SERVER['GAE_INSTANCE'];
        }
        if (is_cloud_run()) {
            return once(static fn () => (new Metadata())->get('instance/id'));
        }

        return false;
    }
}

if (!function_exists('is_g_serverless_development')) {
    function is_g_serverless_development()
    {
        return isset($_SERVER['G_SERVERLESS_DEVELOPMENT']);
    }
}

if (!function_exists('g_serverless_storage_path')) {
    function g_serverless_storage_path($path = '')
    {
        if (is_g_serverless()) {
            $ret = '/tmp/laravel/storage'.($path ? DIRECTORY_SEPARATOR.$path : $path);

            if (is_g_serverless_development()) {
                $ret = '/tmp/laravel/'.$_SERVER['HTTP_HOST'].'/storage'.($path ? DIRECTORY_SEPARATOR.$path : $path);
            }

            @mkdir($ret, 0o755, true);

            return $ret;
        }

        return storage_path($path);
    }
}

if (!function_exists('g_serverless_realpath')) {
    function g_serverless_realpath($path)
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

if (!function_exists('g_serverless_trace_context')) {
    function g_serverless_trace_context(): SpanContext
    {
        return once(static fn () => CloudTraceFormatter::deserialize(
            $_SERVER['HTTP_X_CLOUD_TRACE_CONTEXT'] ?? '',
        ));
    }
}

if (!function_exists('g_serverless_short_trace_id')) {
    function g_serverless_short_trace_id(): string
    {
        return g_serverless_trace_context()->getTraceId();
    }
}

if (!function_exists('g_serverless_trace_id')) {
    function g_serverless_trace_id(): string
    {
        return 'projects/'.g_project().'/traces/'.g_serverless_short_trace_id();
    }
}

if (!function_exists('g_serverless_basic_log')) {
    function g_serverless_basic_log($logName, $severity, $message, $context = []): void
    {
        $record = [
            'severity'                     => $severity,
            'message'                      => $message,
            'context'                      => $context,
            'customLogName'                => $logName,
            'logging.googleapis.com/trace' => g_serverless_trace_id(),
            'time'                         => (new DateTimeImmutable())->format(DateTimeInterface::RFC3339_EXTENDED),
        ];

        @file_put_contents('php://stderr', json_encode($record)."\n", FILE_APPEND);
    }
}

if (!function_exists('diefast')) {
    function diefast($data = null): void
    {
        register_shutdown_function(static function (): void {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
        });

        exit($data);
    }
}

// Deprecated functions

if (!function_exists('gae_project')) {
    function gae_project()
    {
        return g_project();
    }
}

if (!function_exists('gae_service')) {
    function gae_service()
    {
        return g_service();
    }
}

if (!function_exists('gae_version')) {
    function gae_version()
    {
        return g_version();
    }
}

if (!function_exists('gae_instance')) {
    function gae_instance()
    {
        return g_instance();
    }
}

if (!function_exists('is_gae')) {
    function is_gae()
    {
        return is_g_serverless();
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
        return is_g_serverless_development();
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
