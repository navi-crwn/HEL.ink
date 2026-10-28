<?php

namespace Hop\Auth\Http\Controllers;

use Hop\Auth\Ticket;
use Hop\Auth\TicketException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The consumer side of HOP ID.
 *
 *  start    GET /auth/sso?next=/page  → hub /sso/{app}?state=&return_to=
 *  callback GET /auth/sso/callback?ticket= → verify, Auth::loginUsingId(sub), redirect to next
 *  logout   GET /auth/sso/logout?next=  → sign out here, go on to next (the hub's chained logout)
 */
class SsoController
{
    public function start(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        $next = (string) $request->query('next', '');
        $request->session()->put('hop_sso', ['state' => $state, 'next' => self::safePath($next)]);
        $query = http_build_query(['state' => $state, 'return_to' => url(config('hop-auth.routes.callback', '/auth/sso/callback'))]);

        return redirect()->away(config('hop-auth.hub_url').'/sso/'.config('hop-auth.app').'?'.$query);
    }

    public function callback(Request $request): RedirectResponse
    {
        $pending = (array) $request->session()->pull('hop_sso', []);
        $ticket = (string) $request->query('ticket', '');
        if ($ticket === '' || empty($pending['state'])) {
            abort(400, 'Sign-in did not start here. Try again.');
        }
        try {
            $payload = Ticket::verify($ticket, (string) $pending['state']);
        } catch (TicketException $e) {
            abort(403, 'Sign-in ticket refused: '.$e->getMessage());
        }
        // Each ticket works once within its lifetime.
        if (isset($payload['jti']) && ! Cache::add('hop:jti:'.$payload['jti'], 1, 300)) {
            abort(403, 'Sign-in ticket already used.');
        }
        if (($payload['status'] ?? 'active') === 'suspended') {
            abort(403, 'This account is suspended.');
        }
        $user = Auth::loginUsingId((int) $payload['sub'], remember: true);
        if (! $user) {
            abort(403, 'Unknown account.');
        }
        $request->session()->regenerate();
        $request->session()->put('hop_profile', $payload);

        return redirect()->to($pending['next'] ?: config('hop-auth.home', '/'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $next = (string) $request->query('next', '');

        return $next !== '' && self::isHopUrl($next) ? redirect()->away($next) : redirect()->to('/');
    }

    /** Only same-site paths may be a "next" target. */
    public static function safePath(string $path): string
    {
        return $path !== '' && $path[0] === '/' && ! str_starts_with($path, '//') ? $path : '';
    }

    /** The chained logout only follows https links inside the HOP family (the hub and its apps). */
    public static function isHopUrl(string $url): bool
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $hub = (string) parse_url(config('hop-auth.hub_url'), PHP_URL_HOST);
        $base = preg_replace('/^[^.]+\./', '', $hub) ?: $hub;

        return $host !== '' && ($host === $base || str_ends_with($host, '.'.$base));
    }
}
