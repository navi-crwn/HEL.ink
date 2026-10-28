<?php

use Hop\Auth\Http\Controllers\SsoController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get(config('hop-auth.routes.start', '/auth/sso'), [SsoController::class, 'start'])->name('hop.sso.start');
    Route::get(config('hop-auth.routes.callback', '/auth/sso/callback'), [SsoController::class, 'callback'])->name('hop.sso.callback');
    Route::get(config('hop-auth.routes.logout', '/auth/sso/logout'), [SsoController::class, 'logout'])->name('hop.sso.logout');
});
