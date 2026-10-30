<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\SsoController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:register');
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');
    // Unnamed throttles share one counter per IP unless they get their own prefix (3rd argument).
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:5,1,login');
    // Google OAuth Routes
    Route::get('auth/google', [GoogleAuthController::class, 'redirectToGoogle'])
        ->name('auth.google');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback'])
        ->name('auth.google.callback');
    // Terms step before "Continue with Google" while a terms version is
    // required: GET shows the form, POST records the one-shot server-side
    // consent, then the flow continues as a plain Google redirect.
    Route::get('auth/google/consent', [GoogleAuthController::class, 'showConsent'])
        ->name('auth.google.consent');
    Route::post('auth/google/consent', [GoogleAuthController::class, 'storeConsent'])
        ->middleware('throttle:10,1,google-consent')
        ->name('auth.google.consent.store');
    Route::get('suspended', function () {
        return view('auth.suspended');
    })->name('suspended');
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:5,1,forgot-password')
        ->name('password.email');
});
// Outside the guest group: someone who opens the emailed link while still
// signed in (another account, say) is signed out and gets the form.
Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
    ->name('password.reset');
Route::post('reset-password', [NewPasswordController::class, 'store'])
    ->middleware('throttle:10,1,reset-password')
    ->name('password.store');
Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');
    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1,verify-email'])
        ->name('verification.verify');
    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1,verification-send')
        ->name('verification.send');
    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');
    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);
    Route::put('password', [PasswordController::class, 'update'])->name('password.update');
    Route::post('password', [PasswordController::class, 'create'])->name('password.create');
    Route::post('logout', [SsoController::class, 'logout'])
        ->name('logout');
});

// Apps (LinkHop, BioHop) sign the visitor out locally, then send the browser here with a GET.
// It runs the same chained logout as the POST form, also when the hub session has already
// expired, so the visitor always ends signed out everywhere and back on the home page.
Route::get('logout', [SsoController::class, 'logout'])->name('logout.from-app');
