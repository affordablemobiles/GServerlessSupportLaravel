<?php

if (!empty($_ENV['BLOCK_NON_CF'])) {
    define('BLOCK_NON_CF', $_ENV['BLOCK_NON_CF']);
}

if (!defined('BLOCK_NON_CF')) {
    define('BLOCK_NON_CF', false);
}

require __DIR__ . '/../../../../helpers.php';
require __DIR__ . '/ip_in_range.php';

// Only run if we're on GAE
if (is_gae()) {
    // Check if it's an allowed App Engine internal request and run our checks if not.
    $gaeCRON = @$_SERVER['HTTP_X_APPENGINE_CRON'] == 'true' ? true : false;
    $gaeWARM = @$_SERVER['REQUEST_URI'] == '/_ah/warmup' ? true : false;

    // If it isn't coming from a supported AppIdentity, or isn't a CRON.
    if ( ! ( $gaeCRON || $gaeWARM || isset($_SERVER['HTTP_X_APPENGINE_QUEUENAME']) ) ) {
        $is_cf = isset($_SERVER["HTTP_CF_CONNECTING_IP"]);

        $accepted_cf = false;

        if (strpos($_SERVER["REMOTE_ADDR"], ":") === FALSE) {
            // IPv4
            $cf_ip_ranges = require __DIR__.'/cf-ipv4.php';

            foreach ($cf_ip_ranges as $range) {
                if (ipv4_in_range($_SERVER["REMOTE_ADDR"], $range)) {
                    if ($is_cf) {
                        // TODO: Change logging here to support new runtimes.
                        gae_basic_log('cloudflare', 'INFO', 'Cloudflare IP changed from ' . $_SERVER["REMOTE_ADDR"] . ' to ' . $_SERVER["HTTP_CF_CONNECTING_IP"]);
                        $_SERVER["REMOTE_ADDR"] = $_SERVER["HTTP_CF_CONNECTING_IP"];
                        $accepted_cf = true;
                    }
                    break;
                }
            }
        } else {
            // IPv6
            $cf_ip_ranges = require __DIR__.'/cf-ipv6.php';

            foreach ($cf_ip_ranges as $range) {
                if (ipv6_in_range($_SERVER["REMOTE_ADDR"], $range)) {
                    if ($is_cf) {
                        // TODO: Change logging here to support new runtimes.
                        gae_basic_log('cloudflare', 'INFO', 'Cloudflare IP changed from ' . $_SERVER["REMOTE_ADDR"] . ' to ' . $_SERVER["HTTP_CF_CONNECTING_IP"]);
                        $_SERVER["REMOTE_ADDR"] = $_SERVER["HTTP_CF_CONNECTING_IP"];
                        $accepted_cf = true;
                    }
                    break;
                }
            }
        }
        if ( BLOCK_NON_CF ) {
            if ( ! $accepted_cf ) {
                // TODO: Change logging here to support new runtimes.
                syslog(LOG_WARNING, 'Cloudflare protection: BLOCK. Not via Cloudflare');
                header('HTTP/1.1 403 Forbidden');
                die('Permission Denied');
            }
        }
    }
}
