<?php

namespace A1comms\GaeSupportLaravel\Filesystem;

use Closure;
use Mimey\MimeTypes;
use Illuminate\Http\Request;

class StaticFilesMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (is_cloud_run()) {
            $path = public_path() . '/' . request()->path();
            
            if (is_file($path)) {
                return response()->file($path, [
                    'Content-Type' => (new MimeTypes)->getMimeType(
                        pathinfo($path, PATHINFO_EXTENSION)
                    ),
                ]);
            }
        }

        return $next($request);
    }
}
