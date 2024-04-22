<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Trace Providers
    |--------------------------------------------------------------------------
    |
    | This is an array of classes to register for the StackDriver Trace integration,
    | defining runtime trace hooks via the OpenCensus PECL module,
    | so code changes inside the application aren't required.
    |
    | These should implement OpenCensus\Trace\Integrations\IntegrationInterface
    |
    */

    'trace_providers' => [
    ],

    'dev-prefix' => 'dev-',

    'cloud-tasks' => [
        'region' => env('GCT_REGION'),
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
