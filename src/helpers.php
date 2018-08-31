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
        if (is_gae()){
            $ret = '/tmp/laravel'.($path ? DIRECTORY_SEPARATOR.$path : $path);
            mkdir($ret, 0755, true);
            return $ret;
        } else {
            storage_path($path);
        }
    }
}