<?php

declare(strict_types=1);

return [
    // Cloud Tasks configuration, all support Closure values.
    'cloud-tasks' => [
        'via-http'        => !empty(env('CLOUD_TASKS_VIA_HTTP', null)),
        'region'          => env('CLOUD_TASKS_REGION'),
        'service-account' => env('TASK_QUEUE_SERVICE_ACCOUNT', gae_project().'@appspot.gserviceaccount.com'),
        'audience'        => env('OIDC_AUDIENCE'),
    ],

    // Authentication Settings
    'auth' => [
        'middleware' => [
            'audience_map_location' => 'auth.middleware.audience',
        ],
        'firebase' => [
            'cookie_name'         => '__identity_session',
            'cookie_httpOnly'     => true,
            'cookie_sameSite'     => 'strict',
            'logout_redirect'     => '/',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Instance-Local Cache Path
    |--------------------------------------------------------------------------
    |
    | Defines the storage path for the 'instance-scoped' cache store.
    |
    | This store provides a local, per-instance cache (living in /tmp) that
    | is NOT shared between serverless instances. It is used internally
    | by this package (for DB sockets, OIDC keys) and is available for
    | your application via: Cache::store('instance-scoped')->get(...);
    |
    */
    'system_cache_path' => env('G_SERVERLESS_SYSTEM_CACHE_PATH', '/tmp/cache/GServerlessSupportLaravel'),

    /*
    |--------------------------------------------------------------------------
    | Outbound HTTP Client Integration
    |--------------------------------------------------------------------------
    |
    | Configuration for HttpClientServiceProvider. Controls automatic retry
    | of serverless connection timeouts, Google auth token injection, and
    | outbound telemetry/error reporting for third-party HTTP calls.
    |
    */
    'http-client' => [
        // Automatically retry connection/network timeouts using exponential backoff
        'retry' => [
            'enabled' => env('G_SERVERLESS_HTTP_RETRY_ENABLED', true),
        ],

        // Automatically inject Google OIDC/OAuth2 tokens based on audience map
        'auth' => [
            'enabled' => env('G_SERVERLESS_HTTP_AUTH_ENABLED', true),
        ],

        // Automatically report outbound connection drops and upstream 5xx/4xx errors to Error Reporting
        'telemetry' => [
            'enabled'    => env('G_SERVERLESS_HTTP_TELEMETRY_ENABLED', true),
            'report_5xx' => env('G_SERVERLESS_HTTP_REPORT_5XX', true),
            'report_4xx' => [401, 403, 429],

            // Per-host overrides (supports exact hostnames and wildcards like '*.internal.net')
            // 'hosts' => [
            //     'api.example.com' => [
            //         'report_5xx' => true,
            //         'report_4xx' => [400, 401, 403, 429],
            //     ],
            //     '*.internal.net' => [
            //         'report_5xx' => false,
            //     ],
            //     'untracked.example.com' => [
            //         'enabled' => false,
            //     ],
            // ],
            'hosts' => [],
        ],
    ],
];
