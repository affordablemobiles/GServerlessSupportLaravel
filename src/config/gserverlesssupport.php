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
];
