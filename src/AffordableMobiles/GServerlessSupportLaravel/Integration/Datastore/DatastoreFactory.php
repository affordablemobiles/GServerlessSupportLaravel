<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Integration\Datastore;

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
        if (str_contains((string) $ex, 'too much contention on these datastore entities')) {
            Log::info('ExponentialBackoff: retrying datastore operation: too much contention on these datastore entities');

            return true;
        }
        if (str_contains((string) $ex, 'Connection reset by peer')) {
            Log::info('ExponentialBackoff: retrying datastore operation: Connection reset by peer');

            return true;
        }
        if (str_contains((string) $ex, '"status": "UNAVAILABLE"')) {
            Log::info('ExponentialBackoff: retrying datastore operation: UNAVAILABLE');

            return true;
        }
        if (str_contains((string) $ex, '"status": "DEADLINE_EXCEEDED"')) {
            Log::info('ExponentialBackoff: retrying datastore operation: DEADLINE_EXCEEDED');

            return true;
        }

        return false;
    }
}
