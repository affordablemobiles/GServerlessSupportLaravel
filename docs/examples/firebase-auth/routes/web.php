<?php

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

Route::get('/login', function () {
    if ( \Illuminate\Support\Facades\Auth::check() ) {
        return redirect('/');
    }

    return view('login');
})->name('login');

Route::post('/login', [\A1comms\GaeSupportLaravel\Auth\Http\Controllers\Firebase::class, 'login']);
Route::get('/logout', [\A1comms\GaeSupportLaravel\Auth\Http\Controllers\Firebase::class, 'logout']);

Route::middleware(['auth'])->group(function () {
    Route::get('/', function () {
        return view('welcome');
    });
});
