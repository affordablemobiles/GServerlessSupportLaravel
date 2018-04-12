<?php

if (!function_exists('gae_instance')) {
    function gae_instance() {
        if ( is_gae_std() ) {
            return $_SERVER['INSTANCE_ID'];
        } else if ( is_gae_flex() ) {
            return $_SERVER['GAE_INSTANCE'];
        } else {
            return false;
        }
    }
}

if (!function_exists('gae_project')) {
    function gae_project() {
        if ( is_gae_std() ) {
            return explode("~", $_SERVER['APPLICATION_ID'])[1];
        } else if ( is_gae_flex() ) {
            return $_SERVER['GCLOUD_PROJECT'];
        } else {
            return false;
        }
    }
}

if (!function_exists('gae_service')) {
    function gae_service() {
        if ( is_gae_std() ) {
            return $_SERVER['CURRENT_MODULE_ID'];
        } else if ( is_gae_flex() ) {
            return $_SERVER['GAE_SERVICE'];
        } else {
            return false;
        }
    }
}

if (!function_exists('gae_version')) {
    function gae_version() {
        if ( is_gae_std() ) {
            return $_SERVER['CURRENT_VERSION_ID'];
        } else if ( is_gae_flex() ) {
            return $_SERVER['GAE_VERSION'];
        } else {
            return false;
        }
    }
}

if (!function_exists('is_gae')) {
    function is_gae() {
        return ( is_gae_std() || is_gae_flex() );
    }
}

if (!function_exists('is_gae_std')) {
    function is_gae_std() {
        return (
            isset( $_SERVER['SERVER_SOFTWARE'] ) && strpos( $_SERVER['SERVER_SOFTWARE'], 'Google App Engine' ) !== false
        ||
            isset( $_SERVER['SERVER_SOFTWARE'] ) && strpos( $_SERVER['SERVER_SOFTWARE'], 'Development/' ) === 0
        );
    }
}

if (!function_exists('is_gae_flex')) {
    function is_gae_flex() {
        return isset( $_SERVER['GAE_INSTANCE'] );
    }
}

if (!function_exists('app_path')) {
    function app_path() {
        return base_path('app');
    }
}

if (!function_exists('is_lumen')) {
    function is_lumen() {
        return class_exists('\Laravel\Lumen\Application');
    }
}

if (!function_exists('is_production')) {
    function is_production() {
        return strpos( $_SERVER['SERVER_SOFTWARE'], 'Development/' ) === false;
    }
}

