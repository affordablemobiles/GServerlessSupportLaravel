<?php

namespace A1comms\GaeSupportLaravel\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

class TaskCronAuthentication
{
    /**
     * Create a new middleware instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (!is_gae()) {
            Log::info("App Engine Authentication Middleware: Not on App Engine, Bypassing...");
        } elseif ($request->header('X_APPENGINE_CRON', false)) {
            Log::info("App Engine Authentication Middleware: Cron Detected, OK");
        } elseif ($request->header('X_APPENGINE_QUEUENAME', false)) {
            Log::info("App Engine Authentication Middleware: Queue Task Detected (Queue Name: " . $request->header('X_APPENGINE_QUEUENAME', false) . "), OK");
        } else {
            return response('Unauthorized.', 401);
        }

        // return
        return $next($request);
    }
}
