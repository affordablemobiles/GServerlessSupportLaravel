<?php

namespace A1comms\GaeSupportLaravel\Filesystem;

use Closure;
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
                $finfo = finfo_open( FILEINFO_MIME_TYPE );
                $mtype = finfo_file( $finfo, $path );
                finfo_close( $finfo );
                
                return response()->file($path, [
                    'Content-Type' => $mtype,
                ]);
            }
        }

        return $next($request);
    }
}
