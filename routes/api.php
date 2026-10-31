<?php

use App\Http\Controllers\Api\AppAccountController;
use App\Http\Controllers\Api\HopAdminController;
use App\Http\Controllers\Api\HopController;
use App\Http\Controllers\LegalArtifactController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| HOP ID for the apps and Imperium (HOP App Protocol), moved here from LinkHop
|--------------------------------------------------------------------------
| Every call is server to server and signed (VerifyHopSignature: X-HOP-App,
| X-HOP-Timestamp, X-HOP-Nonce, X-HOP-Signature with the app's registry
| secret). Content moderation (/api/hop/manifest, content, reports, x/*,
| settings) is LinkHop's: nginx sends those paths on hub.hel.ink to LinkHop.
*/

// Public, no-store discovery for staged app clients. No signing or user data.
Route::get('/legal/terms', [LegalArtifactController::class, 'metadata'])
    ->name('legal.terms.metadata');

// The apps' own sign-in and sign-up forms use HOP ID accounts.
Route::middleware('throttle:300,1,app-account')->group(function () {
    // PixelHop 3.x, older signature headers.
    Route::post('/pixelhop/login', [AppAccountController::class, 'login'])->defaults('app', 'pixelhop');
    Route::post('/pixelhop/register', [AppAccountController::class, 'register'])->defaults('app', 'pixelhop');
    // HOP App Protocol.
    Route::middleware('hop.signed')->prefix('apps/{app}')->where(['app' => '[a-z0-9-]{1,32}'])->group(function () {
        Route::post('/login', [AppAccountController::class, 'login']);
        Route::post('/register', [AppAccountController::class, 'register']);
    });
});

Route::middleware(['hop.signed', 'throttle:600,1,hop'])->prefix('hop')->group(function () {
    Route::get('/profile/{user}', [HopController::class, 'profile']);
    Route::get('/profiles', [HopController::class, 'profiles']);
    Route::get('/apps', [HopController::class, 'apps']);
    Route::get('/health', [HopAdminController::class, 'health']);
    // The admin API: only for an app registered with the admin_client capability (Imperium).
    Route::middleware('hop.admin_client')->group(function () {
        Route::get('/manifest', [HopAdminController::class, 'manifest']);
        Route::get('/stats', [HopAdminController::class, 'stats']);
        Route::get('/users', [HopAdminController::class, 'users']);
        $missing = fn () => response()->json(['ok' => false, 'error' => 'not_found', 'message' => 'No such user.'], 404);
        Route::get('/users/{user}', [HopAdminController::class, 'user'])->missing($missing);
        Route::post('/users/{user}/actions', [HopAdminController::class, 'act'])->missing($missing);
        Route::get('/audit', [HopAdminController::class, 'audit']);
        Route::get('/search', [HopAdminController::class, 'search']);
    });
});
