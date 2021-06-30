<?php

namespace A1comms\GaeSupportLaravel\Filesystem;

use Closure;
use Mimey\MimeTypes;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
            $path = public_path() . '/' . $request->path();
            
            if (is_file($path)) {
                return $this->file($path, [
                    'Content-Type' => (new MimeTypes)->getMimeType(
                        pathinfo($path, PATHINFO_EXTENSION)
                    ),
                ]);
            }
        }

        return $next($request);
    }

    /**
     * Return the raw contents of a binary file.
     *
     * @param  \SplFileInfo|string  $file
     * @param  array  $headers
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function file($file, array $headers = [])
    {
        return new BinaryFileResponse($file, 200, $headers);
    }
}
