<?php

use App\Http\AdminMiddleware;
use App\Http\Controllers\SsoController;
use App\Http\Middleware\AutoLogout;
use App\Http\Middleware\CheckSuspended;
use App\Http\Middleware\ContentSecurityPolicyMiddleware;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Http\Middleware\NoStoreWhenSignedIn;
use App\Http\Middleware\ProtectPermanentSuperadmin;
use App\Http\Middleware\RemoveWwwPrefix;
use App\Http\Middleware\RequireHopAdminClient;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\VerifyHopSignature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(RemoveWwwPrefix::class);
        // First in the group: CSRF, throttle and route-binding failures must render in the visitor's language.
        $middleware->prependToGroup('web', SetLocale::class);
        $middleware->appendToGroup('web', [
            AutoLogout::class,
            CheckSuspended::class,
            ContentSecurityPolicyMiddleware::class,
            NoStoreWhenSignedIn::class,
            ProtectPermanentSuperadmin::class,
        ]);
        // The language cookie is plain text (en, id, …), read before decryption; so is its clean-up marker.
        $middleware->encryptCookies(except: [SetLocale::COOKIE, SetLocale::MARKER]);
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            '2fa' => EnsureTwoFactorAuthenticated::class,
            'hop.signed' => VerifyHopSignature::class,
            'hop.admin_client' => RequireHopAdminClient::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A silent SSO check (prompt=none) promises no page: over the throttle it gets an error at the app's callback.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            return $request->routeIs('sso.ticket') && $request->query('prompt') === 'none'
                ? SsoController::silentReply($request, 'temporarily_unavailable')
                : null;
        });
        // An expired CSRF token (session timed out while the page stayed open) is not an
        // attack worth a bare "419 Page Expired" page. CSRF stays on; only the reply changes.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || ! $e->getPrevious() instanceof TokenMismatchException || $request->expectsJson()) {
                return null;
            }
            if ($request->routeIs('logout')) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->with('status', __('You have been signed out.'));
            }

            return redirect()->back()
                ->withInput($request->except(['_token', 'password', 'password_confirmation', 'current_password', 'token', 'code', 'recovery_code']))
                ->with('session_expired', __('Your session expired. Try again.'));
        });
    })->create();
