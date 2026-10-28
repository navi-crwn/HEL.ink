<?php

namespace App\Http\Middleware;

use App\Support\HopApps;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class ContentSecurityPolicyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Per-request nonce: added to every <script> tag (Vite::cspNonce()) and @vite output.
        $nonce = Vite::useCspNonce();
        $response = $next($request);
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://challenges.cloudflare.com https://fonts.bunny.net https://static.cloudflareinsights.com",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            "img-src 'self' data: https: blob:",
            "font-src 'self' https://fonts.bunny.net data:",
            "connect-src 'self' https://challenges.cloudflare.com",
            "frame-src 'self' https://challenges.cloudflare.com",
            "object-src 'none'",
            "base-uri 'self'",
            // Signing in from an app ends in a redirect to the app after the
            // form post; form-action applies to that redirect too.
            trim("form-action 'self' ".implode(' ', HopApps::origins())),
            "frame-ancestors 'none'",
            'upgrade-insecure-requests',
        ];
        $response->headers->set('Content-Security-Policy', implode('; ', $csp));
        $response->headers->set('Content-Security-Policy-Report-Only', implode('; ', [
            "script-src 'nonce-{$nonce}' 'strict-dynamic' https: 'unsafe-inline'",
            "script-src-attr 'none'",
            "object-src 'none'",
            "base-uri 'self'",
        ]));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        return $response;
    }
}
