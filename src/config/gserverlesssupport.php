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
];
