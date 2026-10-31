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
        // https://hel.ink serves the shared HOP assets (fonts, floating tiles), also to hub.hel.ink.
        $csp = [
            "default-src 'self'",
            // 'unsafe-eval': Alpine.js evaluates x-data/x-on expressions with new Function().
            "script-src 'self' https://hel.ink 'unsafe-inline' 'unsafe-eval' https://challenges.cloudflare.com https://static.cloudflareinsights.com",
            "style-src 'self' https://hel.ink 'unsafe-inline'",
            "img-src 'self' data: https: blob:",
            "font-src 'self' https://hel.ink data:",
            "connect-src 'self' data: https://challenges.cloudflare.com",
            "frame-src 'self' https://challenges.cloudflare.com",
            "object-src 'none'",
            "base-uri 'self'",
            // Signing in from an app ends in a redirect to the app after the
            // form post; form-action applies to that redirect too.
            // The logout chain ends on the public home page (hel.ink), so that host is allowed too.
            trim("form-action 'self' ".implode(' ', array_unique(array_filter([...HopApps::origins(), self::origin((string) config('hop.public_url'))])))),
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

    private static function origin(string $url): ?string
    {
        $p = parse_url($url);

        return ! empty($p['scheme']) && ! empty($p['host']) ? $p['scheme'].'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '') : null;
    }
}
