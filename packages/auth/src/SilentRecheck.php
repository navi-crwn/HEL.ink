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
 * Safeguards: no redirect (and no sign-out: the session stays trusted) unless the hub's health URL answers 2xx (the
 * verdict is cached), at most one
 * attempt per `cooldown` seconds (a loop cannot start), and any hub `error` keeps the session and waits `retry_after`.
 */
class SilentRecheck
{
    public const VERIFIED = 'hop_verified_at';

    public const BLOCKED_UNTIL = 'hop_recheck_until';

    /** @var (callable(string): ?int)|null Test hook: replaces the HTTP call of the probe; returns the status or null. */
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

        $dest = strtolower((string) $request->header('Sec-Fetch-Dest', 'document'));

        return $request->isMethod('GET') && $dest === 'document' && ! $request->ajax() && ! $request->expectsJson()
            && ! str_contains($purpose, 'prefetch') && ! str_contains($purpose, 'preview');
    }

    /**
     * The hub answers its health URL with a 2xx within the timeout. Anything else (a 502 from the web server in front
     * of a dead PHP, a timeout, no connection) means "not reachable": the session stays trusted and nobody is
     * redirected. The verdict is cached (`probe_ttl`), so a sick hub costs one probe per window, and the next page
     * load after the cache expires tries again.
     */
    private static function hubReachable(): bool
    {
        if (! config('hop-auth.recheck.probe', true) && self::$probe === null) {
            return true;
        }
        $url = (string) config('hop-auth.hub_url').(string) config('hop-auth.recheck.probe_path', '/up');

        return (bool) Cache::remember('hop:hub-reachable:'.sha1($url), (int) config('hop-auth.recheck.probe_ttl', 60), static function () use ($url): bool {
            $status = self::$probe !== null ? (self::$probe)($url) : self::fetchStatus($url);

            return $status !== null && $status >= 200 && $status < 300;
        });
    }

    /** HTTP status of a GET (no redirects followed), or null when there was no answer in time. */
    private static function fetchStatus(string $url): ?int
    {
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'timeout' => (float) config('hop-auth.recheck.probe_timeout', 2),
            'follow_location' => 0,
            'ignore_errors' => true,
            'header' => "Accept: */*\r\nUser-Agent: hop-auth-probe\r\n",
        ]]);
        $stream = @fopen($url, 'r', false, $context);
        $headers = $http_response_header ?? [];
        if (is_resource($stream)) {
            fclose($stream);
        }

        return isset($headers[0]) && preg_match('#^HTTP/\S+\s+(\d{3})#', $headers[0], $m) ? (int) $m[1] : null;
    }
}
