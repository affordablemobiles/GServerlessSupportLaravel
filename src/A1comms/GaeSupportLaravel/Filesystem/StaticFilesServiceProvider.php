<?php

namespace A1comms\GaeSupportLaravel\Filesystem;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class StaticFilesServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->map();
    }

    public function register()
    {
    }

    private function map()
    {
        Route::fallback(function () {
            $path = public_path() . '/' . request()->path();
            
            if (!is_file($path)) {
                throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
            }

            return response()->file($path);
        });
    }
}
