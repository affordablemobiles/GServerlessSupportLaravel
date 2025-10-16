<?php

declare(strict_types=1);

use Illuminate\Http\Request;

return [
    /*
    |--------------------------------------------------------------------------
    | Enable or Disable Error Reporter
    |--------------------------------------------------------------------------
    |
    | This switch allows you to completely turn the client-side error
    | reporting functionality on or off for the entire application.
    |
    */
    'enabled' => false,

    /*
    |--------------------------------------------------------------------------
    | Reporting Endpoint Route URI
    |--------------------------------------------------------------------------
    |
    | This is the URI where the client-side JavaScript will POST error reports.
    | It is automatically registered by the package's service provider.
    |
    */
    'route_uri' => '/js-error-report',

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | You can apply a prefix or middleware to the reporting endpoint. By
    | default, no prefix is used and it is part of the 'web' middleware
    | group. CSRF protection is automatically handled.
    |
    */
    'route_prefix'     => '',
    'route_middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Endpoint Authentication
    |--------------------------------------------------------------------------
    |
    | Define the authentication logic for the reporting endpoint. By default,
    | it performs a simple same-origin check (Host vs. Referer). You can
    | override this with your own closure for custom logic, or set it to
    | `null` to disable authentication entirely. The closure must
    | return `true` to allow the request to proceed.
    |
    */
    'authentication' => static function (Request $request): bool {
        $referer = $request->header('referer');
        if (!$referer) {
            // No referer, deny the request.
            return false;
        }

        $refererHost = parse_url($referer, PHP_URL_HOST);
        $requestHost = $request->getHost();

        // Allow the request only if the referer host matches the request host.
        return $refererHost === $requestHost;
    },

    /*
    |--------------------------------------------------------------------------
    | User Identifier
    |--------------------------------------------------------------------------
    |
    | Configure how the user identifier is retrieved for error reports. This
    | can be an array specifying a source ('cookie', 'header', 'session')
    | and a key, or a Closure that receives the request object. Set to
    | null to disable user tracking.
    |
    | Example (Array):
    | 'user_identifier' => ['source' => 'cookie', 'key' => '__session_id'],
    |
    | Example (Closure):
    | 'user_identifier' => function (\Illuminate\Http\Request $request) {
    |     return $request->user()?->id ?? $request->cookie('guest_id');
    | },
    |
    */
    'user_identifier' => [
        'source' => null, // Can be 'cookie', 'header', 'session', or null.
        // 'key'    => ''
    ],
];
