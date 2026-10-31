<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeEmail;
use App\Models\User;
use App\Services\LoginRecorder;
use App\Services\NotificationService;
use App\Support\AfterLogin;
use App\Support\Legal;
use App\Support\Username;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    protected $notificationService;

    /** Session key of the one-shot OAuth terms consent, and how long it lives. */
    private const CONSENT_KEY = 'legal_oauth_consent';

    private const CONSENT_TTL_SECONDS = 600;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * GET /auth/google/consent: the terms step before "Continue with Google"
     * when a version is required. Server-side only in effect: accepting here
     * plants a short-lived, single-use consent flag in the session — the
     * Google round trip itself stays a plain GET (JS is never trusted to
     * carry consent).
     */
    public function showConsent(Request $request): View|RedirectResponse
    {
        $version = Legal::requiredVersion();
        Legal::assertUsable($version);
        if ($version === null) {
            return $this->startOauth($request);
        }

        return view('auth.oauth-consent', [
            'termsVersion' => $version,
            'termsArtifact' => Legal::artifact($version),
        ]);
    }

    /**
     * POST /auth/google/consent: records the one-shot consent flag after an
     * explicit server-verified acceptance (CSRF-protected form, exact required
     * version). Continues into the normal Google redirect.
     */
    public function storeConsent(Request $request)
    {
        $requiredVersion = Legal::requiredVersion();
        Legal::assertUsable($requiredVersion);
        $request->validate([
            'accept' => ['required', 'accepted'],
            'terms_version' => $requiredVersion === null ? ['prohibited'] : ['required', 'in:'.$requiredVersion],
        ], [
            'accept.accepted' => 'Please accept the current terms.',
            'accept.required' => 'Please accept the current terms.',
            'terms_version.required' => 'Please accept the current terms.',
            'terms_version.in' => 'Please accept the current terms.',
        ]);
        // One array key avoids Laravel session dot-notation collisions.
        $request->session()->put(self::CONSENT_KEY, [
            'version' => $requiredVersion,
            'at' => now()->getTimestamp(),
        ]);

        return redirect()->route('auth.google', ['from_register' => 1]);
    }

    public function redirectToGoogle()
    {
        if (request()->get('from_app') == '1') {
            // From an app's "Continue with Google": sign in or sign up, then
            // back to the app (the intended URL is its ticket step).
            session(['google_oauth_from' => 'app']);
        } elseif (request()->get('from_register') == '1') {
            session(['google_oauth_from' => 'register']);
        } else {
            session(['google_oauth_from' => 'login']);
        }

        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            $fromPage = session('google_oauth_from', 'login');
            session()->forget('google_oauth_from');
            // Accounts are matched by email, so only trust addresses Google has verified.
            $raw = (array) ($googleUser->user ?? []);
            if (! ($raw['email_verified'] ?? $raw['verified_email'] ?? false)) {
                return redirect()->route('login')->with('error', 'Your Google email address is not verified.');
            }
            $user = User::where('email', $googleUser->email)
                ->orWhere('google_id', $googleUser->id)
                ->first();
            if ($fromPage === 'login' && ! $user) {
                return redirect()->route('login')
                    ->with('error', 'Account not found. Please register first to continue.')
                    ->with('google_email', $googleUser->email);
            }
            if ($fromPage === 'register' && $user) {
                return redirect()->route('login')->with('error', 'Account already exists. Please login instead.');
            }
            if ($user) {
                // Existing account (sign-in or linking): unchanged. No new
                // consent is recorded for a pre-gate account at sign-in.
                if (! $user->google_id) {
                    $user->update([
                        'google_id' => $googleUser->id,
                        'avatar' => $googleUser->avatar,
                    ]);
                }
                // Google vouches for this address (checked above), and it is the
                // account's email: accounts made before the fix below get verified here.
                if (! $user->hasVerifiedEmail()) {
                    $user->markEmailAsVerified();
                }
                if ($user->isSuspended()) {
                    return redirect()->route('suspended')->with('error', 'Your account has been suspended.');
                }
                Auth::login($user, true);
                app(LoginRecorder::class)->record($user, request()->ip(), request()->userAgent(), 'google');

                return redirect()->intended(AfterLogin::home($user))->with('success', 'Welcome back, '.$user->name.'!');
            } else {
                // New account. While a terms version is required, this branch
                // only runs after an explicit, server-verified, single-use
                // consent from the terms step — never from a bare Google link.
                $requiredVersion = Legal::requiredVersion();
                Legal::assertUsable($requiredVersion);
                $acceptedVersion = $requiredVersion === null ? null : $this->consumeConsent();
                if ($requiredVersion !== null && $acceptedVersion === null) {
                    return redirect()->route('register')
                        ->with('error', 'Please accept the terms before creating your account.');
                }
                $user = DB::transaction(function () use ($googleUser, $acceptedVersion): User {
                    $user = User::create([
                        'name' => $googleUser->name,
                        'email' => $googleUser->email,
                        'google_id' => $googleUser->id,
                        'avatar' => $googleUser->avatar,
                        'password' => Hash::make('TEMPORARY_GOOGLE_OAUTH_NO_PASSWORD_'.Str::random(32)), // Temporary password
                    ]);
                    // Google already verified the address. email_verified_at is not
                    // mass assignable, so create() silently dropped it before.
                    $user->forceFill(['email_verified_at' => now(), 'username' => Username::suggest($user->name ?: $user->email, $user)])->save();
                    if ($acceptedVersion !== null) {
                        Legal::record($user, Legal::SOURCE_OAUTH, $acceptedVersion);
                    }

                    return $user;
                });
                try {
                    $this->notificationService->notifyNewUser([
                        'name' => $user->name,
                        'email' => $user->email,
                        'registration_method' => 'Google OAuth',
                        'registered_at' => $user->created_at->format('Y-m-d H:i:s'),
                    ]);
                } catch (Exception $e) {
                    \Log::error('Failed to send new user notification', [
                        'error' => $e->getMessage(),
                        'user_id' => $user->id,
                    ]);
                }
                try {
                    \Mail::to($user->email)->send(new WelcomeEmail($user, true));
                } catch (Exception $e) {
                    \Log::error('Failed to send welcome email', [
                        'error' => $e->getMessage(),
                        'user_id' => $user->id,
                    ]);
                }
                Auth::login($user, true);

                if ($fromPage === 'app') {
                    return redirect()->intended(route('onboarding'));
                }

                return redirect()->route('onboarding')->with('success', 'Welcome to HOP'.', '.$user->name.'!');
            }
        } catch (Exception $e) {
            \Log::error('Google OAuth Error: '.$e->getMessage());

            return redirect()->route('login')
                ->with('error', 'Unable to login with Google. You may not have registered yet. Please register first.');
        }
    }

    /**
     * Reads and deletes the one-shot consent flag; null when absent (never
     * planted), already consumed, expired, or naming anything other than the
     * version required right now.
     */
    private function consumeConsent(): ?string
    {
        $session = request()->session();
        $consent = $session->get(self::CONSENT_KEY);
        $session->forget(self::CONSENT_KEY);
        if (! is_array($consent) || ! is_int($consent['at'] ?? null)) {
            return null;
        }
        $version = $consent['version'] ?? null;
        $at = $consent['at'];
        if (! is_string($version) || $version === '') {
            return null;
        }
        $age = now()->getTimestamp() - $at;
        if ($age < 0 || $age > self::CONSENT_TTL_SECONDS) {
            return null;
        }
        if (Legal::requiredVersion() === null || $version !== Legal::requiredVersion()) {
            return null;
        }

        return $version;
    }

    private function startOauth(Request $request)
    {
        return app()->call([self::class, 'redirectToGoogle']);
    }
}
