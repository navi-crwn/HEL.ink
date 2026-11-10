<?php

namespace Hop\Auth;

use Hop\Auth\Http\Controllers\SsoController;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Silent re-check of the signed-in account against the hub (docs/silent-sso.md of the hub: `prompt=none`).
 *
 * The app session is "fresh" for `hop-auth.recheck.max_age` seconds after the hub vouched for it (the sign-in
 * callback, or a later silent answer). After that, a plain page load (GET, not XHR, not a prefetch) makes one
 * round trip to the hub: same account, carry on; another account, switch to it; `login_required`, sign out here.
 * Nothing is stored in the database: the timestamps live in the session.
 *
 * Safeguards: no redirect while the hub does not answer on its port (and the probe result is cached), at most one
 * attempt per `cooldown` seconds (a loop cannot start), and any hub `error` keeps the session and waits `retry_after`.
 */
class SilentRecheck
{
    public const VERIFIED = 'hop_verified_at';

    public const BLOCKED_UNTIL = 'hop_recheck_until';

    /** @var (callable(): bool)|null Test hook: replaces the reachability probe. */
    public static $probe = null;

    public static function enabled(): bool
    {
        return (bool) config('hop-auth.recheck.enabled', true) && (int) config('hop-auth.recheck.max_age', 300) > 0;
    }

    /** A redirect to the hub when this request should re-check the account, otherwise null. */
    public static function redirectIfStale(Request $request): ?RedirectResponse
    {
        if (! self::enabled() || ! $request->hasSession() || ! self::isPage($request) || ! $request->user()) {
            return null;
        }
        $session = $request->session();
        $now = time();
        if ($now - (int) $session->get(self::VERIFIED, 0) < (int) config('hop-auth.recheck.max_age', 300)) {
            return null;
        }
        if ($now < (int) $session->get(self::BLOCKED_UNTIL, 0) || ! self::hubReachable()) {
            return null;
        }
        $session->put(self::BLOCKED_UNTIL, $now + (int) config('hop-auth.recheck.cooldown', 30));
        $state = Str::random(40);
        $session->put('hop_sso', ['state' => $state, 'next' => SsoController::safePath($request->getRequestUri()), 'silent' => true]);

        return redirect()->away(SsoController::hubUrl($state, true));
    }

    public static function markVerified(Session $session): void
    {
        $session->put(self::VERIFIED, time());
        $session->forget(self::BLOCKED_UNTIL);
    }

    /** The hub said "not now" (or anything but a verdict): keep the session, ask again later. */
    public static function backOff(Session $session): void
    {
        $session->put(self::BLOCKED_UNTIL, time() + (int) config('hop-auth.recheck.retry_after', 300));
    }

    /** Only a page the browser navigates to: not a form post, not XHR/fetch for JSON, not a prefetch. */
    private static function isPage(Request $request): bool
    {
        $purpose = strtolower((string) $request->header('Purpose', '').' '.(string) $request->header('Sec-Purpose', ''));

        return $request->isMethod('GET') && ! $request->ajax() && ! $request->expectsJson()
            && ! str_contains($purpose, 'prefetch') && ! str_contains($purpose, 'preview');
    }

    /** A TCP connection to the hub within the timeout; cached for a short while so a dead hub costs one probe, not one per page. */
    private static function hubReachable(): bool
    {
        if (self::$probe !== null) {
            return (bool) (self::$probe)();
        }
        if (! config('hop-auth.recheck.probe', true)) {
            return true;
        }
        $parts = parse_url((string) config('hop-auth.hub_url'));
        $host = (string) ($parts['host'] ?? '');
        if ($host === '') {
            return false;
        }
        $tls = ($parts['scheme'] ?? 'https') === 'https';
        $port = (int) ($parts['port'] ?? ($tls ? 443 : 80));

        return (bool) Cache::remember('hop:hub-reachable', 30, static function () use ($host, $port, $tls): bool {
            $socket = @stream_socket_client(($tls ? 'tls://' : 'tcp://').$host.':'.$port, $errno, $error, (float) config('hop-auth.recheck.probe_timeout', 1.5));
            if (! is_resource($socket)) {
                return false;
            }
            fclose($socket);

            return true;
        });
    }
}
