<?php

declare(strict_types=1);
use IPLib\Factory;

if (!empty($_ENV['BLOCK_NON_CF'])) {
    define('BLOCK_NON_CF', $_ENV['BLOCK_NON_CF']);
}

if (!defined('BLOCK_NON_CF')) {
    define('BLOCK_NON_CF', false);
}

require __DIR__.'/../../../../helpers.php';

// Only run if we're on GAE
if (is_gae()) {
    // Check if it's an allowed App Engine internal request and run our checks if not.
    $gaeCRON = 'true'        === @$_SERVER['HTTP_X_APPENGINE_CRON'] ? true : false;
    $gaeWARM = '/_ah/warmup' === @$_SERVER['REQUEST_URI'] ? true : false;

    // If it isn't coming from a supported AppIdentity, or isn't a CRON.
    if (!($gaeCRON || $gaeWARM || isset($_SERVER['HTTP_X_APPENGINE_QUEUENAME']))) {
        $is_cf = isset($_SERVER['HTTP_CF_CONNECTING_IP']);

        $address = Factory::parseAddressString($_SERVER['REMOTE_ADDR']);

        $accepted_cf = false;

        if (!str_contains($_SERVER['REMOTE_ADDR'], ':')) {
            // IPv4
            $cf_ip_ranges = require __DIR__.'/cf-ipv4.php';

            foreach ($cf_ip_ranges as $raw_range) {
                $range = Factory::parseRangeString($raw_range);
                if ($range->contains($address)) {
                    if ($is_cf) {
                        // TODO: Change logging here to support new runtimes.
                        gae_basic_log('cloudflare', 'INFO', 'Cloudflare IP changed from '.$_SERVER['REMOTE_ADDR'].' to '.$_SERVER['HTTP_CF_CONNECTING_IP'], [
                            'REMOTE_ADDR'           => $_SERVER['REMOTE_ADDR'],
                            'HTTP_CF_CONNECTING_IP' => $_SERVER['HTTP_CF_CONNECTING_IP'],
                        ]);
                        $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
                        $accepted_cf            = true;
                    }

                    break;
                }
            }
        } else {
            // IPv6
            $cf_ip_ranges = require __DIR__.'/cf-ipv6.php';

            foreach ($cf_ip_ranges as $raw_range) {
                $range = Factory::parseRangeString($raw_range);
                if ($range->contains($address)) {
                    if ($is_cf) {
                        // TODO: Change logging here to support new runtimes.
                        gae_basic_log('cloudflare', 'INFO', 'Cloudflare IP changed from '.$_SERVER['REMOTE_ADDR'].' to '.$_SERVER['HTTP_CF_CONNECTING_IP'], [
                            'REMOTE_ADDR'           => $_SERVER['REMOTE_ADDR'],
                            'HTTP_CF_CONNECTING_IP' => $_SERVER['HTTP_CF_CONNECTING_IP'],
                        ]);
                        $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
                        $accepted_cf            = true;
                    }

                    break;
                }
            }
        }
        if (BLOCK_NON_CF) {
            if (!$accepted_cf) {
                // TODO: Change logging here to support new runtimes.
                syslog(LOG_WARNING, 'Cloudflare protection: BLOCK. Not via Cloudflare');
                header('HTTP/1.1 403 Forbidden');

                exit('Permission Denied');
            }
        }
    }
}
