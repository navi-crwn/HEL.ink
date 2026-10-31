<?php

use App\Http\Controllers\Admin\AdminAnnouncementController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\BrandAssetController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LauncherController;
use App\Http\Controllers\LegalArtifactController;
use App\Http\Controllers\MarketingPageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SsoController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\TwoFactorController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Two hosts, one app
|--------------------------------------------------------------------------
| hel.ink (hop.public_host): the HOP home page, marketing pages, status.
|   No /l/*, /b/* or /api/* here: nginx sends those to LinkHop and BioHop
|   (deploy/nginx/hel.ink.conf). /login and /register bounce to the hub.
| hub.hel.ink (hop.hub_host): accounts (HOP ID), launcher, SSO, settings, admin.
|
| In dev and tests both hosts are "localhost", so every route answers on one
| host; the public-host group is registered last and only adds what the hub
| host does not have.
*/

$brandFile = '[a-z0-9-]+\.(png|svg|ico)';
$brand = function () use ($brandFile) {
    Route::get('/brand/{v}/logo.png', [BrandAssetController::class, 'show'])->defaults('file', 'logo.png')->where('v', '[a-f0-9]{8}')->name('brand.logo');
    Route::get('/brand/{v}/logo-dark.png', [BrandAssetController::class, 'show'])->defaults('file', 'logo-dark.png')->where('v', '[a-f0-9]{8}')->name('brand.logo.dark');
    Route::get('/brand/{v}/favicon.png', [BrandAssetController::class, 'show'])->defaults('file', 'favicon.png')->where('v', '[a-f0-9]{8}')->name('brand.favicon');
    Route::get('/brand/{v}/{file}', [BrandAssetController::class, 'show'])->where(['v' => '[a-f0-9]{8}', 'file' => $brandFile])->name('brand.asset');
    Route::get('/brand/{file}', [BrandAssetController::class, 'legacy'])->where('file', $brandFile);
};
$marketing = function () {
    Route::get('/about', [MarketingPageController::class, 'about'])->name('about');
    Route::get('/contact', [MarketingPageController::class, 'contact'])->name('contact');
    Route::get('/privacy', [MarketingPageController::class, 'privacy'])->name('privacy');
    Route::get('/terms', [MarketingPageController::class, 'terms'])->name('terms');
    Route::get('/tos', fn () => redirect()->route('terms', [], 301));
    Route::get('/plans', [MarketingPageController::class, 'plans'])->name('plans');
    Route::get('/help', [MarketingPageController::class, 'help'])->name('help');
    Route::get('/faq', [MarketingPageController::class, 'faq'])->name('faq');
    Route::get('/products', [MarketingPageController::class, 'products'])->name('products');
    Route::get('/status', StatusController::class)->name('status');
    Route::get('/status.json', [StatusController::class, 'json'])->name('status.json');
    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show'])->name('announcements.show');
};

// ---------------------------------------------------------------- hub.hel.ink
// Brand assets and marketing pages answer on both hosts under one route name each,
// so they are registered once, without a domain (route:cache refuses duplicate names).
$brand();
$marketing();

// Exact local bytes for a registered version; there is no remote URL fetch.
Route::get('/legal/terms/{version}', [LegalArtifactController::class, 'show'])
    ->where('version', '[A-Za-z0-9][A-Za-z0-9._-]{0,63}')->name('legal.terms.artifact');

Route::domain(config('hop.hub_host'))->group(function () use ($marketing) {
    Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'))->name('hub.home');
    Route::get('/onboarding', fn () => view('auth.onboarding'))->name('onboarding');
    Route::post('/onboarding/skip', fn () => redirect()->route('dashboard'))->name('onboarding.skip');
    Route::post('/onboarding/set-password', [ProfileController::class, 'createPasswordFromOnboarding'])->name('onboarding.set-password');
    Route::get('/goodbye', fn () => view('goodbye'))->name('goodbye');
    // "Continue with Google" in the apps: works for guests too (see SsoController::viaGoogle).
    Route::get('/sso/{app}/google', [SsoController::class, 'viaGoogle'])->where('app', '[a-z0-9-]{1,32}')->middleware('throttle:30,1,sso-google')->name('sso.google');
    Route::get('/csrf-token', fn () => response()->json(['token' => csrf_token()])->header('Cache-Control', 'no-store, private'))
        ->middleware('throttle:30,1,csrf-token')->name('csrf.token');

    Route::middleware(['auth'])->group(function () {
        Route::get('/two-factor-challenge', [TwoFactorController::class, 'show'])->name('two-factor.show');
        Route::post('/two-factor-challenge', [TwoFactorController::class, 'verify'])->middleware('throttle:5,1,two-factor')->name('two-factor.verify');
    });
    Route::middleware(['auth', '2fa'])->group(function () {
        Route::get('/sso/{app}', [SsoController::class, 'ticket'])->where('app', '[a-z0-9-]{1,32}')->middleware('throttle:30,1,sso')->name('sso.ticket');
        Route::get('/apps/{app}', [SsoController::class, 'open'])->where('app', '[a-z0-9-]{1,32}')->name('apps.open');
        Route::get('/dashboard', LauncherController::class)->name('dashboard');
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('/profile/username-check', [ProfileController::class, 'usernameCheck'])->middleware('throttle:60,1,username-check')->name('profile.username-check');
        Route::post('/profile/avatar', [ProfileController::class, 'storeAvatar'])->name('profile.avatar');
        Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
        Route::post('/settings/export-account', [SettingsController::class, 'exportAccount'])->middleware('throttle:5,1,export-account')->name('settings.export.account');
        Route::post('/settings/2fa/enable', [TwoFactorController::class, 'enable'])->name('settings.2fa.enable');
        Route::post('/settings/2fa/confirm', [TwoFactorController::class, 'confirm'])->name('settings.2fa.confirm');
        Route::delete('/settings/2fa/disable', [TwoFactorController::class, 'disable'])->name('settings.2fa.disable');
        Route::post('/settings/2fa/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('settings.2fa.recovery-codes');
        Route::post('/api-tokens', [ApiTokenController::class, 'store'])->middleware('verified')->name('api-tokens.store');
        Route::delete('/api-tokens/{apiToken}', [ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');
        Route::get('/api-docs', [ApiTokenController::class, 'docs'])->name('api-docs');
    });
    Route::middleware(['auth', '2fa', 'admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', fn () => redirect()->route('admin.users.index'))->name('home');
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::post('/users/{user}/suspend', [AdminUserController::class, 'suspend'])->name('users.suspend');
        Route::post('/users/{user}/activate', [AdminUserController::class, 'activate'])->name('users.activate');
        Route::post('/users/{user}/role', [AdminUserController::class, 'role'])->name('users.role');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
        Route::get('/announcements', [AdminAnnouncementController::class, 'index'])->name('announcements.index');
        Route::post('/announcements', [AdminAnnouncementController::class, 'store'])->name('announcements.store');
        Route::put('/announcements/{announcement}', [AdminAnnouncementController::class, 'update'])->name('announcements.update');
        Route::delete('/announcements/{announcement}', [AdminAnnouncementController::class, 'destroy'])->name('announcements.destroy');
    });
    require __DIR__.'/auth.php';
});

// ---------------------------------------------------------------- hel.ink
Route::domain(config('hop.public_host'))->group(function () {
    Route::get('/', HomeController::class)->name('home');
    if (config('hop.public_host') !== config('hop.hub_host')) {
        // Accounts live on the hub host.
        Route::get('/login', fn () => redirect()->to(hub_url('login')));
        // Before the split, apps (Imperium, NoteHop, …) started sign-in at hel.ink/sso/{app}; it is the hub's now.
        Route::get('/sso/{path}', fn (Request $request, string $path) => redirect()->away(
            hub_url('sso/'.$path).($request->getQueryString() ? '?'.$request->getQueryString() : '')
        ))->where('path', '[a-z0-9-]{1,32}(/google)?');
        Route::get('/register', fn () => redirect()->to(hub_url('register')));
        Route::get('/dashboard', fn () => redirect()->to(hub_url('dashboard')));
    }
});
