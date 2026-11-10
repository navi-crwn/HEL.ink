<?php

namespace Hop\Auth\Http\Controllers;

use Hop\Auth\SilentRecheck;
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
 *
 * The callback also answers the silent re-check (`prompt=none`, see SilentRecheck): a ticket for the same
 * account only refreshes the session's timestamp, a ticket for another account switches to it, and
 * `error=login_required` signs out here.
 */
class SsoController
{
    public function start(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        $next = self::queryString($request, 'next');
        $request->session()->put('hop_sso', ['state' => $state, 'next' => self::safePath($next)]);

        return redirect()->away(self::hubUrl($state));
    }

    /** The hub's sign-in URL for this app; `$silent` adds `prompt=none` (never a sign-in page, see the hub's docs/silent-sso.md). */
    public static function hubUrl(string $state, bool $silent = false): string
    {
        $query = ['state' => $state, 'return_to' => url(config('hop-auth.routes.callback', '/auth/sso/callback'))];
        if ($silent) {
            $query['prompt'] = 'none';
        }

        return config('hop-auth.hub_url').'/sso/'.config('hop-auth.app').'?'.http_build_query($query);
    }

    public function callback(Request $request): RedirectResponse
    {
        $pending = (array) $request->session()->pull('hop_sso', []);
        if (self::queryString($request, 'error') !== '') {
            return $this->hubError($request, $pending);
        }
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
        if (! empty($pending['silent']) && Auth::check() && (string) Auth::id() === (string) $payload['sub']) {
            // Same account the hub has: nothing changes for the visitor, the session is just vouched for again.
            $request->session()->put('hop_profile', $payload);
            SilentRecheck::markVerified($request->session());

            return redirect()->to($pending['next'] ?: config('hop-auth.home', '/'));
        }
        $user = Auth::loginUsingId((int) $payload['sub'], remember: true);
        if (! $user) {
            abort(403, 'Unknown account.');
        }
        $request->session()->regenerate();
        $request->session()->put('hop_profile', $payload);
        SilentRecheck::markVerified($request->session());

        return redirect()->to($pending['next'] ?: config('hop-auth.home', '/'));
    }

    /**
     * The hub answered with `?error=&state=` (only ever to a `prompt=none` request). The state must be the one this
     * session started. `login_required`: nobody is signed in at the hub any more, so sign out here and go on to the
     * page (a public page shows as a guest; a protected one starts the normal sign-in). Any other error keeps the
     * session and waits before asking again.
     */
    private function hubError(Request $request, array $pending): RedirectResponse
    {
        $state = self::queryString($request, 'state');
        if (empty($pending['silent']) || empty($pending['state']) || $state === '' || ! hash_equals((string) $pending['state'], $state)) {
            abort(400, 'Sign-in did not start here. Try again.');
        }
        $next = $pending['next'] ?: config('hop-auth.home', '/');
        if (self::queryString($request, 'error') === 'login_required') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->to($next);
        }
        SilentRecheck::backOff($request->session());

        return redirect()->to($next);
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
