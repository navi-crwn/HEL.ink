<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LoginRecorder;
use App\Services\SsoTicket;
use App\Support\HopApp;
use App\Support\HopApps;
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
    /** GET /sso/{app}: sign the current user in to the app. */
    public function ticket(Request $request, string $app): RedirectResponse
    {
        [$hopApp, $state, $returnTo] = $this->checked($request, $app);

        /** @var User $user */
        $user = $request->user();
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

        return redirect()->away($hopApp->startUrl($request->query('to') ? '/'.ltrim((string) $request->query('to'), '/') : null));
    }

    /**
     * POST /logout: sign out of the hub, then of each app that has a logout
     * endpoint, one redirect after another (each app sends the browser on to
     * the next with ?next=). Apps without one just see their session end on
     * its own.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to(self::logoutChain(route('home')));
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
        $state = (string) $request->query('state', '');
        $returnTo = (string) $request->query('return_to', '');
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
