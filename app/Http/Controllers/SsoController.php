<?php

namespace App\Http\Controllers;

use App\Jobs\SendHopEvent;
use App\Models\User;
use App\Services\LoginRecorder;
use App\Services\SsoTicket;
use App\Support\AuthTime;
use App\Support\HopApp;
use App\Support\HopApps;
use App\Support\Input;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

/**
 * The hub as SSO issuer for every app in config/hop.php (LinkHop, BioHop,
 * PixelHop). The apps have no sign-in of their own: they send the visitor
 * to GET /sso/{app}?state=&return_to=, the hub signs them in as usual and
 * hands back a short-lived ticket (SsoTicket) that the app verifies with the
 * shared secret and turns into Auth::loginUsingId($sub), since the users
 * table is the same.
 */
class SsoController extends Controller
{
    /**
     * GET /sso/{app}: sign the current user in to the app.
     *
     * `prompt=none` (silent re-check, never shows a page): with a hub session this is exactly the normal
     * ticket; without one (none, a suspended account, or a two-factor check still open) the visitor is sent
     * 302 to the app's registered callback with `error=login_required` and the same `state`, and nothing is
     * kept as the intended URL. Without `prompt=none` a guest is sent to sign in, as before. The route is
     * outside the auth group so this controller decides, in this order, the same things auth and 2fa did.
     *
     * `max_age=<seconds>` (and `prompt=login`, which is `max_age=60`): the app wants a sign-in at the hub no older
     * than that. A hub session whose `auth_time` is older, or has none, is ended here and the visitor sees the
     * sign-in page; the same SSO address is kept as the place to go next, so after signing in the ticket follows
     * at once, carrying the fresh `auth_time`. With `prompt=none` that case answers `login_required` instead.
     */
    public function ticket(Request $request, string $app): RedirectResponse
    {
        [$hopApp, $state, $returnTo] = $this->checked($request, $app);

        /** @var User|null $user */
        $user = $request->user();
        $silent = $request->query('prompt') === 'none';
        $maxAge = AuthTime::maxAge($request->query('max_age'), $request->query('prompt'));
        $stale = $maxAge !== null && time() - AuthTime::get() > $maxAge;
        $needsCode = $user && $user->two_factor_secret && $user->two_factor_confirmed_at && ! $request->session()->get('2fa_verified', false);
        if ($silent && (! $user || $needsCode || $user->isSuspended() || $stale)) {
            return self::silentReply($request, 'login_required');
        }
        if (! $user) {
            return redirect()->guest(route('login'));
        }
        if ($needsCode) {
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('two-factor.show');
        }
        if ($user->isSuspended()) {
            return redirect()->route('suspended');
        }
        // Only a verified address gets a ticket: a Google link says nothing about the address the account holds now.
        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }
        if ($stale) {
            return $this->signInAgain($request);
        }
        $ticket = SsoTicket::issue($user, $hopApp, $state);
        app(LoginRecorder::class)->record($user, $request->ip(), $request->userAgent(), $hopApp->name);

        return redirect()->away($returnTo.'?'.http_build_query(['ticket' => $ticket]));
    }

    /**
     * Ends this browser's hub session (and only this one) and shows the sign-in page, with the current address
     * as the place to go after it. The "remember me" cookie of this browser is dropped too, or it would sign the
     * person straight back in without proving anything; the remember token itself is not rotated, so the cookies
     * of the person's other devices keep working.
     */
    private function signInAgain(Request $request): RedirectResponse
    {
        // Only this device: the remember token is kept, and the guard stays logged out for the rest of this request
        // (a plain forgetUser() lets a later $request->user() read the "remember me" cookie again).
        Auth::guard('web')->logoutCurrentDevice();
        $request->session()->invalidate();
        $request->session()->put('url.intended', $request->fullUrl());

        return redirect()->route('login')->with('status', __('Please sign in again to confirm it is you.'));
    }

    /**
     * The silent answer `<registered callback>?error=<code>&state=<state>`, or null when the app, the state or the
     * return address do not check out (then the normal error applies). Used for login_required and, over the
     * throttle, temporarily_unavailable.
     */
    public static function silentReply(Request $request, string $error): ?RedirectResponse
    {
        $app = HopApps::find((string) $request->route('app'));
        $state = Input::text($request->query('state'));
        if (! $app || ! $app->isConfigured() || preg_match('/^[A-Za-z0-9_-]{16,128}$/', $state) !== 1 || Input::text($request->query('return_to')) !== $app->callbackUrl()) {
            return null;
        }

        return redirect()->away($app->callbackUrl().'?'.http_build_query(['error' => $error, 'state' => $state]));
    }

    /**
     * GET /sso/{app}/google: the app's "Continue with Google" button. Signed
     * in already: straight on to the ticket. Otherwise Google first, then back
     * to the ticket step through Laravel's intended URL.
     */
    public function viaGoogle(Request $request, string $app): RedirectResponse
    {
        [$hopApp, $state, $returnTo] = $this->checked($request, $app);
        abort_if(! config('services.google.client_id'), 404);
        $ticket = route('sso.ticket', array_filter([
            'app' => $hopApp->slug,
            'state' => $state,
            'return_to' => $returnTo,
            'max_age' => $request->query('max_age'),
            'prompt' => $request->query('prompt'),
        ], fn ($v) => is_string($v) && $v !== ''));
        if ($request->user()) {
            return redirect()->to($ticket);
        }
        $request->session()->put('url.intended', $ticket);

        return redirect()->route('auth.google', ['from_app' => 1]);
    }

    /** GET /apps/{app}: the launcher's "Open" button; lands in the app signed in. */
    public function open(Request $request, string $app): RedirectResponse
    {
        $hopApp = HopApps::find($app);
        abort_if(! $hopApp || ! $hopApp->isConfigured(), 404);

        $to = ltrim(Input::text($request->query('to')), '/');
        if ($to !== '' && preg_match('#^[A-Za-z0-9/_-]+$#', $to) !== 1) {
            abort(404);
        }

        return redirect()->away($hopApp->startUrl($to !== '' ? '/'.$to : $hopApp->defaultNext));
    }

    /**
     * POST /logout (and GET /logout from an app): sign out of the hub and end the person's sessions
     * in the apps. Back channel first: every app that listens for events gets a queued
     * session.revoked and stays out of the browser chain. Only the apps that were not queued
     * (no `events` capability, not configured, or the queue refused) are signed out by redirect,
     * one after another (each app sends the browser on to the next with ?next=). When the queue
     * cannot take the events at all, the full chain runs, as before.
     */
    public function logout(Request $request): RedirectResponse
    {
        // The id is gone once the guard has logged out, and the event needs it.
        $userId = Auth::guard('web')->id();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $queued = $userId !== null ? self::revokeAppSessions((string) $userId) : [];

        return redirect()->to(self::logoutChain(self::returnTarget($request), $queued));
    }

    /**
     * Queues session.revoked for every app that listens and returns their slugs. A queued event does not
     * depend on the browser; the queue retries it when the app comes back. If the queue throws, nothing
     * can be relied on and [] is returned, so the caller falls back to the full redirect chain.
     *
     * @return list<string>
     */
    private static function revokeAppSessions(string $userId): array
    {
        try {
            return SendHopEvent::broadcast('session.revoked', ['sub' => $userId, 'hop_id' => $userId]);
        } catch (\Throwable $e) {
            // A queue that cannot take the job must not break signing out.
            report($e);

            return [];
        }
    }

    /**
     * Where signing out ends: the `return` address an app sent (its home URL), but only when
     * its origin is a configured family app; anything else (other hosts, userinfo tricks,
     * relative or scheme-less values) falls back to the hub home. Never an open redirect.
     */
    public static function returnTarget(Request $request): string
    {
        $return = $request->input('return');
        if (is_string($return) && $return !== '' && strlen($return) <= 2048 && ! str_contains($return, "\x5c") && preg_match('/[[:cntrl:][:space:]]/', $return) !== 1) {
            $p = parse_url($return);
            if ($p && in_array($p['scheme'] ?? '', ['http', 'https'], true) && isset($p['host']) && ! isset($p['user']) && ! isset($p['pass'])) {
                $origin = $p['scheme'].'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '');
                if (in_array(strtolower($origin), array_map('strtolower', HopApps::origins()), true)) {
                    return $return;
                }
            }
        }

        return route('home');
    }

    /**
     * The first URL of the chained logout, ending at $finally.
     *
     * @param  list<string>  $signedOutByEvent  slugs of apps whose session.revoked event is queued; they are left out
     */
    public static function logoutChain(string $finally, array $signedOutByEvent = []): string
    {
        $next = $finally;
        foreach (array_reverse(HopApps::configured()) as $app) {
            if (in_array($app->slug, $signedOutByEvent, true)) {
                continue;
            }
            if ($url = $app->logoutUrl()) {
                $next = $url.'?'.http_build_query(['next' => $next]);
            }
        }

        return $next;
    }

    /**
     * The app, and the state and return address it sent, checked.
     *
     * @return array{HopApp, string, string}
     */
    private function checked(Request $request, string $slug): array
    {
        $app = HopApps::find($slug);
        abort_if(! $app || ! $app->isConfigured(), 404);
        $state = Input::text($request->query('state'));
        $returnTo = Input::text($request->query('return_to'));
        abort_unless(preg_match('/^[A-Za-z0-9_-]{16,128}$/', $state) === 1, 400, 'Invalid state.');
        // Only ever send tickets to the app's own callback.
        abort_unless($returnTo === $app->callbackUrl(), 400, 'Invalid return address.');

        return [$app, $state, $returnTo];
    }

    /**
     * The app the visitor was signing in to while they register, verify
     * their email or finish onboarding (Laravel keeps the /sso/{app} address
     * as the "intended" URL meanwhile), or null.
     */
    public static function cameFrom(Request $request): ?HopApp
    {
        $intended = (string) $request->session()->get('url.intended', '');
        if (preg_match('#/sso/([a-z0-9-]{1,32})(?:[/?]|$)#', $intended, $m) !== 1) {
            return null;
        }
        $app = HopApps::find($m[1]);

        return $app && $app->isConfigured() ? $app : null;
    }

    /** Where to send someone who signed up on their way to an app: a fresh sign-in round trip. */
    public static function continueTo(Request $request, HopApp $app): RedirectResponse
    {
        $request->session()->forget('url.intended');

        return redirect()->to(self::continueUrl($app));
    }

    public static function continueUrl(HopApp $app): string
    {
        return route('apps.open', ['app' => $app->slug]);
    }
}
