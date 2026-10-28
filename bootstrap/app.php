<?php

use App\Http\AdminMiddleware;
use App\Http\Middleware\AutoLogout;
use App\Http\Middleware\CheckSuspended;
use App\Http\Middleware\ContentSecurityPolicyMiddleware;
use App\Http\Middleware\EnsureGoogleUserHasPassword;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Http\Middleware\RemoveWwwPrefix;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(RemoveWwwPrefix::class);
        $middleware->appendToGroup('web', [
            AutoLogout::class,
            CheckSuspended::class,
            EnsureGoogleUserHasPassword::class,
            ContentSecurityPolicyMiddleware::class,
        ]);
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            '2fa' => EnsureTwoFactorAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
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

                return redirect()->route('login')->with('status', 'You have been signed out.');
            }

            return redirect()->back()
                ->withInput($request->except(['_token', 'password', 'password_confirmation', 'current_password', 'token', 'code', 'recovery_code']))
                ->with('session_expired', 'Sesi habis, silakan coba lagi.');
        });
    })->create();
