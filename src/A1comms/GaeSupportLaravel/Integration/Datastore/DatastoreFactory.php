<?php

namespace A1comms\GaeSupportLaravel\Integration\Datastore;

use GDS;
use Illuminate\Support\Facades\Log;

class DatastoreFactory
{
    public static function make($namespace = null)
    {
        return new GDS\Gateway\GRPCv1(gae_project(), $namespace);
    }

    public static function shouldRetry($ex, $retryAttempt = 1)
    {
        if (strpos((string)$ex, 'too much contention on these datastore entities') !== false) {
            Log::info('ExponentialBackoff: retrying datastore operation: too much contention on these datastore entities');
            return true;
        } elseif (strpos((string)$ex, 'Connection reset by peer') !== false) {
            Log::info('ExponentialBackoff: retrying datastore operation: Connection reset by peer');
            return true;
        } elseif (strpos((string)$ex, '"status": "UNAVAILABLE"') !== false) {
            Log::info('ExponentialBackoff: retrying datastore operation: UNAVAILABLE');
            return true;
        }

        return false;
    }
}
