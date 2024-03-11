<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Filesystem;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Mimey\MimeTypes;

class StaticFilesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (is_cloud_run()) {
            $this->map();
        }
    }

    public function register(): void {}

    private function map(): void
    {
        Route::fallback(static function () {
            $path = public_path().'/'.request()->path();

            if (!is_file($path)) {
                throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
            }

            return response()->file($path, [
                'Content-Type' => (new MimeTypes())->getMimeType(
                    pathinfo($path, PATHINFO_EXTENSION)
                ),
            ]);
        });
    }
}
