<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Trace instrumentation
    |--------------------------------------------------------------------------
    |
    | This is an array of classes to register for the Trace integration,
    | defining runtime trace hooks via the OpenTelemetry PECL module,
    | so code changes inside the application aren't required.
    |
    | These should implement AffordableMobiles\GServerlessSupportLaravel\Trace\Instrumentation\InstrumentationInterface
    |
    */

    'trace_instrumentation' => [
        // ...
    ],

    'cloud-tasks' => [
        'region'          => env('CLOUD_TASKS_REGION'),
        'service-account' => env('TASK_QUEUE_SERVICE_ACCOUNT', gae_project().'@appspot.gserviceaccount.com'),
        'audience'        => env('OIDC_AUDIENCE'),
    ],

    // Authentication Settings
    'auth' => [
        'firebase' => [
            'cookie_name'         => '__identity_session',
            'cookie_httpOnly'     => true,
            'cookie_sameSite'     => 'strict',
            'logout_redirect'     => '/',
        ],
    ],
];
