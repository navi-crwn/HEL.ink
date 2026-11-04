<?php

use Hop\Auth\Http\Controllers\EventController;
use Illuminate\Support\Facades\Route;

// Not in the `web` group: the hub is not a browser, so no session, cookies or CSRF token.
Route::post((string) config('hop-auth.routes.events'), EventController::class)->middleware('hop.event')->name('hop.events');
