<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Filesystem;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class StaticFilesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->map();
    }

    public function register(): void
    {
    }

    private function map(): void
    {
        Route::fallback(function () {
            $path = public_path().'/'.request()->path();

            if (!is_file($path)) {
                throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
            }

            return response()->file($path);
        });
    }
}
