<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Trace\Integration\LowLevel;

use A1comms\GaeSupportLaravel\Auth\Guard;
use OpenCensus\Trace\Integrations\IntegrationInterface;

class LaravelAuth implements IntegrationInterface
{
    public static function load(): void
    {
        if (!\extension_loaded('opencensus')) {
            trigger_error('opencensus extension required to load Laravel integrations.', E_USER_WARNING);

            return;
        }

        opencensus_trace_method(Guard\BaseGuard::class, 'returnUser', [
            'name'       => 'laravel/auth/returnUser',
            'attributes' => [],
        ]);

        opencensus_trace_method(Guard\AppEngine_Guard::class, 'validate', [
            'name'       => 'laravel/auth/guard/appengine',
            'attributes' => [],
        ]);

        opencensus_trace_method(Guard\IAP_Guard::class, 'validate', [
            'name'       => 'laravel/auth/guard/iap',
            'attributes' => [],
        ]);

        opencensus_trace_method(Guard\OIDC_Guard::class, 'validate', [
            'name'       => 'laravel/auth/guard/oidc',
            'attributes' => [],
        ]);

        opencensus_trace_method(Guard\OAuth2_Guard::class, 'validate', [
            'name'       => 'laravel/auth/guard/oauth2',
            'attributes' => [],
        ]);
    }
}
