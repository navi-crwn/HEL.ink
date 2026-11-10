<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSuspended
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && auth()->user()->isSuspended()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            // A silent re-check never shows a page: the guest it now is gets login_required from the ticket step.
            if ($request->routeIs('sso.ticket') && $request->query('prompt') === 'none') {
                return $next($request);
            }

            return redirect()->route('suspended')
                ->with('error', 'Your account has been suspended. Please contact administrator.');
        }

        return $next($request);
    }
}
