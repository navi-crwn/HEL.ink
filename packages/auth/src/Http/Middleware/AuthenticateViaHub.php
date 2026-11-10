<?php

namespace Hop\Auth\Http\Middleware;

use Closure;
use Hop\Auth\SilentRecheck;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `hop.auth`: like `auth`, but a guest is sent through the hub instead of to
 * a local login page, and comes back to the page they asked for. A signed-in
 * visitor whose session the hub has not vouched for lately is re-checked
 * silently (see SilentRecheck) on page loads.
 */
class AuthenticateViaHub
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            return SilentRecheck::redirectIfStale($request) ?? $next($request);
        }
        if ($request->expectsJson()) {
            abort(401);
        }
        $start = config('hop-auth.routes.start', '/auth/sso');

        return redirect()->to($start.'?next='.rawurlencode($request->getRequestUri()));
    }
}
