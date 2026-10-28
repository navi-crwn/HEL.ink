<?php

namespace Hop\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `hop.auth`: like `auth`, but a guest is sent through the hub instead of to
 * a local login page, and comes back to the page they asked for.
 */
class AuthenticateViaHub
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            return $next($request);
        }
        if ($request->expectsJson()) {
            abort(401);
        }
        $start = config('hop-auth.routes.start', '/auth/sso');

        return redirect()->to($start.'?next='.rawurlencode($request->getRequestUri()));
    }
}
