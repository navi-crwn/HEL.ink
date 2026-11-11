<?php

namespace Hop\Auth\Http\Middleware;

use Closure;
use Hop\Auth\SilentRecheck;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `hop.recheck`: for pages that do not require a sign-in (a public landing page that shows "Dashboard" to a
 * signed-in visitor): confirms with the hub, once per `hop-auth.recheck.max_age`, that the account in this
 * session is still the one signed in at the hub. A guest passes untouched. `hop.auth` does the same check itself.
 */
class RecheckHubSession
{
    public function handle(Request $request, Closure $next): Response
    {
        return SilentRecheck::redirectIfStale($request) ?? $next($request);
    }
}
