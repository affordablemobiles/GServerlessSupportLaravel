<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Filesystem;

use Closure;
use Illuminate\Http\Request;
use Mimey\MimeTypes;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StaticFilesMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (is_cloud_run()) {
            $path = public_path().'/'.$request->path();

            if (is_file($path)) {
                return $this->file($path, [
                    'Content-Type' => (new MimeTypes())->getMimeType(
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
     * @param \SplFileInfo|string $file
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function file($file, array $headers = [])
    {
        return new BinaryFileResponse($file, 200, $headers);
    }
}
