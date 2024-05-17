<?php

declare(strict_types=1);

use AffordableMobiles\GServerlessSupportLaravel\Auth\Http\Controllers\Firebase;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/login', static function () {
    if (Auth::check()) {
        return redirect('/');
    }

    return view('login');
})->name('login');

Route::post('/login', [Firebase::class, 'login']);
Route::get('/logout', [Firebase::class, 'logout']);

Route::middleware(['auth'])->group(static function (): void {
    Route::get('/', static fn () => view('welcome'));
});
