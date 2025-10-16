<?php

declare(strict_types=1);

use AffordableMobiles\GServerlessSupportLaravel\Integration\ErrorReporting\ClientSideJavaScript\Http\Controllers\ErrorReporterController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

Route::post(
    Config::get('js-error-reporter.route_uri'),
    [ErrorReporterController::class, 'report']
)
    ->name('js-error-reporter.report')
    ->withoutMiddleware([ValidateCsrfToken::class])
;
