<?php

if (!function_exists('is_gae')) {
    function is_gae() {
        return isset($_SERVER['GAE_INSTANCE']);
    }
}

if (!function_exists('is_gae_std')) {
    function is_gae_std() {
        if (isset($_SERVER['GAE_ENV']))
        {
            if ($_SERVER['GAE_ENV'] == "standard")
            {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('is_gae_std_legacy')) {
    function is_gae_std_legacy() {
        return (
            isset( $_SERVER['SERVER_SOFTWARE'] ) && strpos( $_SERVER['SERVER_SOFTWARE'], 'Google App Engine' ) !== false
        ||
            isset( $_SERVER['SERVER_SOFTWARE'] ) && strpos( $_SERVER['SERVER_SOFTWARE'], 'Development/' ) === 0
        );
    }
}

if (!function_exists('is_gae_production')) {
    function is_gae_production() {
        return ( is_gae() && (strpos( $_SERVER['SERVER_SOFTWARE'], 'Development/' ) === false) );
    }
}

if (!function_exists('is_gae_flex')) {
    function is_gae_flex() {
        if (isset($_SERVER['GOOGLE_CLOUD_PROJECT']))
        {
            if (!isset($_SERVER['GAE_ENV'])){
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('gae_instance')) {
    function gae_instance() {
        if ( is_gae() ) {
            return $_SERVER['GAE_INSTANCE'];
        } else {
            return false;
        }
    }
}

if (!function_exists('gae_project')) {
    function gae_project() {
        if ( is_gae() ) {
            return $_SERVER['GOOGLE_CLOUD_PROJECT'];
        } else {
            return false;
        }
    }
}

if (!function_exists('gae_service')) {
    function gae_service() {
        if ( is_gae() ) {
            return $_SERVER['GAE_SERVICE'];
        } else {
            return false;
        }
    }
}

if (!function_exists('gae_version')) {
    function gae_version() {
        if ( is_gae() ) {
            return $_SERVER['GAE_VERSION'];
        } else {
            return false;
        }
    }
}

if (!function_exists('gae_storage_path')) {
    function gae_storage_path($path = '')
    {
        if (is_gae() || defined('IS_GAE')){
            $ret = '/tmp/laravel/storage'.($path ? DIRECTORY_SEPARATOR.$path : $path);
            @mkdir($ret, 0755, true);
            return $ret;
        } else {
            return storage_path($path);
        }
    }
}

if (!function_exists('app_path')) {
    function app_path($path = '') {
        $extra = empty($path) ? '' : ('/' . $path);
        return base_path('app').$extra;
    }
}

if (!function_exists('is_lumen')) {
    function is_lumen() {
        return class_exists('\Laravel\Lumen\Application');
    }
}