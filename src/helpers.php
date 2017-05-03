<?php

if (!function_exists('gae_instance')) {
    function gae_instance() {
        return empty($_SERVER['GAE_INSTANCE']) ? '' : $_SERVER['GAE_INSTANCE'];
    }
}

if (!function_exists('gae_project')) {
    function gae_project() {
        return empty($_SERVER['GCLOUD_PROJECT']) ? '' : $_SERVER['GCLOUD_PROJECT'];
    }
}

if (!function_exists('gae_service')) {
    function gae_service() {
        return empty($_SERVER['GAE_SERVICE']) ? '' : $_SERVER['GAE_SERVICE'];
    }
}

if (!function_exists('gae_version')) {
    function gae_version() {
        return empty($_SERVER['GAE_VERSION']) ? '' : $_SERVER['GAE_VERSION'];
    }
}
