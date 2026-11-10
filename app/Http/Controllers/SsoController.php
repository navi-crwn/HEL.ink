<?php

namespace App\Http\Controllers;

use App\Jobs\SendHopEvent;
use App\Models\User;
use App\Services\LoginRecorder;
use App\Services\SsoTicket;
use App\Support\HopApp;
use App\Support\HopApps;
use App\Support\Input;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
     */
    public function ticket(Request $request, string $app): RedirectResponse
    {
        [$hopApp, $state, $returnTo] = $this->checked($request, $app);

        /** @var User|null $user */
        $user = $request->user();
        $silent = $request->query('prompt') === 'none';
        $needsCode = $user && $user->two_factor_secret && $user->two_factor_confirmed_at && ! $request->session()->get('2fa_verified', false);
        if ($silent && (! $user || $needsCode || $user->isSuspended())) {
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
        // Google already verified the address; everyone else confirms it first.
        if (! $user->hasVerifiedEmail() && ! $user->google_id) {
            return redirect()->route('verification.notice');
        }
        $ticket = SsoTicket::issue($user, $hopApp, $state);
        app(LoginRecorder::class)->record($user, $request->ip(), $request->userAgent(), $hopApp->name);

        return redirect()->away($returnTo.'?'.http_build_query(['ticket' => $ticket]));
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
        $ticket = route('sso.ticket', ['app' => $hopApp->slug, 'state' => $state, 'return_to' => $returnTo]);
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
     * POST /logout: sign out of the hub, tell every app that listens for events
     * to end this person's sessions, then sign out of each app that has a logout
     * endpoint, one redirect after another (each app sends the browser on to
     * the next with ?next=). Apps without one just see their session end on
     * its own.
     */
    public function logout(Request $request): RedirectResponse
    {
        // The id is gone once the guard has logged out, and the event needs it.
        $userId = Auth::guard('web')->id();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        if ($userId !== null) {
            self::revokeAppSessions((string) $userId);
        }

        return redirect()->to(self::logoutChain(self::returnTarget($request)));
    }

    /**
     * The redirect chain below only signs out the apps the browser reaches: it stops at the
     * first app that is down and leaves every app after it signed in. A queued session.revoked
     * event does not depend on the browser; the queue retries it when the app comes back.
     */
    private static function revokeAppSessions(string $userId): void
    {
        try {
            SendHopEvent::broadcast('session.revoked', ['sub' => $userId, 'hop_id' => $userId]);
        } catch (\Throwable $e) {
            // A queue that cannot take the job must not break signing out.
            report($e);
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

    /** The first URL of the chained logout, ending at $finally. */
    public static function logoutChain(string $finally): string
    {
        $next = $finally;
        foreach (array_reverse(HopApps::configured()) as $app) {
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
