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
        $next = self::queryString($request, 'next');
        $request->session()->put('hop_sso', ['state' => $state, 'next' => self::safePath($next)]);
        $query = http_build_query(['state' => $state, 'return_to' => url(config('hop-auth.routes.callback', '/auth/sso/callback'))]);

        return redirect()->away(config('hop-auth.hub_url').'/sso/'.config('hop-auth.app').'?'.$query);
    }

    public function callback(Request $request): RedirectResponse
    {
        $pending = (array) $request->session()->pull('hop_sso', []);
        $ticket = self::queryString($request, 'ticket');
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
        $next = self::queryString($request, 'next');

        return $next !== '' && self::isHopUrl($next) ? redirect()->away($next) : redirect()->to('/');
    }

    /** A query value as a string; ?x[]=1 is not a string and counts as absent (it would otherwise be a 500). */
    private static function queryString(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? $value : '';
    }

    /** Only same-site paths may be a "next" target. */
    public static function safePath(string $path): string
    {
        return $path !== '' && $path[0] === '/' && ! str_starts_with($path, '//') && ! preg_match('/[\x00-\x1f\x7f\\\\]/', $path) ? $path : '';
    }

    /** The chained logout only follows https links to a trusted HOP host (see `trusted_hosts` in the config). */
    public static function isHopUrl(string $url): bool
    {
        if ($url === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
            return false;
        }
        $parts = parse_url($url);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || ! in_array($host, self::trustedHosts(), true)) {
            return false;
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        return $scheme === 'https' || ($scheme === 'http' && self::isLocalHost($host));
    }

    /**
     * Every host the chained logout may go to: the configured list, the hub, this app,
     * and the app URLs of the shared config/hop.php, so installs on their own domain keep working.
     *
     * @return array<int, string>
     */
    public static function trustedHosts(): array
    {
        $entries = array_merge(
            (array) config('hop-auth.trusted_hosts', []),
            [config('hop-auth.hub_url'), config('app.url'), config('hop.public_url'), config('hop.hub_url')],
            array_column((array) config('hop.apps', []), 'url'),
        );
        $hosts = [];
        foreach ($entries as $entry) {
            if (! is_string($entry) || ($entry = strtolower(trim($entry))) === '') {
                continue;
            }
            $host = (string) parse_url(str_contains($entry, '://') ? $entry : '//'.$entry, PHP_URL_HOST);
            if ($host !== '') {
                $hosts[] = $host;
            }
        }

        return array_values(array_unique($hosts));
    }

    private static function isLocalHost(string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1', '[::1]'], true);
    }
}
