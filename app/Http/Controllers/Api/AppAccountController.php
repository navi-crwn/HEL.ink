<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeEmail;
use App\Models\HopApp;
use App\Models\LoginHistory;
use App\Models\User;
use App\Support\HopSignature;
use App\Support\Legal;
use App\Support\Username;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules;

use function Illuminate\Support\defer;

/**
 * Lets a HOP app's own sign-in and sign-up forms use HOP ID accounts, so an
 * account made in either place works everywhere without visiting HOP ID.
 *
 * Server to server only. New apps call /api/apps/{app}/login|register signed
 * with the HOP App Protocol headers (HopSignature). PixelHop 3.x still calls
 * /api/pixelhop/login|register with its older signature
 * (X-PixelHop-Timestamp + X-PixelHop-Signature =
 * base64url(HMAC-SHA256(secret, "pixelhop-api|{timestamp}|{body}"))); both
 * use the app's secret from the registry. Turnstile runs in the app. The app
 * sends the visitor's IP in the signed body, so this side applies the same IP
 * bans as the web sign-in and limits failed attempts per email and per
 * visitor IP (not per app server, which every visitor shares).
 *
 * Accounts with two-factor codes, and accounts whose email is not confirmed
 * yet, are not signed in here: the app sends those people through the
 * normal /sso/{app} flow instead, like the web.
 *
 * Moved here from LinkHop with the accounts. IP bans and the watchlist live in
 * LinkHop's tables in the shared database and are read with the query builder.
 */
class AppAccountController extends Controller
{
    private const MAX_AGE = 120;

    public function login(Request $request, string $app = 'pixelhop'): JsonResponse
    {
        if ($denied = $this->verifySignature($request, $app) ?? $this->bannedIp($request)) {
            return $denied;
        }
        $hopApp = $request->attributes->get('hop_app');
        $email = strtolower(trim((string) $request->json('email', '')));
        $password = (string) $request->json('password', '');
        $ip = $this->visitorIp($request);
        $emailKey = 'app-login:'.sha1($email);
        $ipKey = 'app-login-ip:'.$ip;
        if (RateLimiter::tooManyAttempts($emailKey, 10) || ($ip !== '' && RateLimiter::tooManyAttempts($ipKey, 30))) {
            return response()->json(['error' => 'too_many_attempts'], 429);
        }
        $user = User::where('email', $email)->first();
        if (! $user || ! $user->password || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($emailKey, 15 * 60);
            if ($ip !== '') {
                RateLimiter::hit($ipKey, 15 * 60);
            }

            return response()->json(['error' => 'bad_credentials'], 401);
        }
        RateLimiter::clear($emailKey);
        if ($user->isSuspended()) {
            if ($ip !== '') {
                $this->watch($ip, $user->id, $hopApp->name.' login attempt by suspended user: '.$user->email);
            }

            return response()->json(['error' => 'suspended'], 403);
        }
        if ($user->two_factor_secret && $user->two_factor_confirmed_at) {
            return response()->json(['error' => 'two_factor'], 409);
        }
        // Same rule as the /sso/pixelhop ticket: confirm the address first.
        if (! $user->hasVerifiedEmail() && ! $user->google_id) {
            return response()->json(['error' => 'unverified'], 403);
        }
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $ip])->saveQuietly();
        LoginHistory::create(['user_id' => $user->id, 'ip_address' => $ip ?: null, 'browser' => $hopApp->name, 'logged_in_at' => now()]);
        LoginHistory::keepLatest($user->id);

        return response()->json(['user' => $this->payload($user, $hopApp)]);
    }

    public function register(Request $request, string $app = 'pixelhop'): JsonResponse
    {
        if ($denied = $this->verifySignature($request, $app) ?? $this->bannedIp($request)) {
            return $denied;
        }
        $hopApp = $request->attributes->get('hop_app');
        $requiredVersion = Legal::requiredVersion();
        if (! Legal::isUsable($requiredVersion)) {
            return response()->json(['error' => 'not_configured', 'message' => 'Account registration is temporarily unavailable.'], 503);
        }
        $data = [
            'name' => trim((string) $request->json('name', '')),
            'email' => strtolower(trim((string) $request->json('email', ''))),
            'password' => (string) $request->json('password', ''),
            'tos_accepted' => (bool) $request->json('tos_accepted', false),
            // Sent by the app inside the signed body, so it is authentic. While
            // the gate is inactive it must be absent (backwards compatible with
            // PixelHop 3.x); when active it must name exactly the version
            // required right now — the app cannot pick an arbitrary version.
            'terms_version' => (string) $request->json('terms_version', ''),
        ];
        $v = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'indisposable', 'unique:'.User::class],
            'password' => ['required', Rules\Password::defaults()],
            'tos_accepted' => ['accepted'],
            'terms_version' => $requiredVersion === null ? ['prohibited'] : ['required', 'in:'.$requiredVersion],
        ], [
            'email.indisposable' => 'Disposable email addresses are not allowed.',
            'email.unique' => 'An account with this email already exists. Sign in instead.',
            'tos_accepted.accepted' => 'Please accept the terms.',
            'terms_version.required' => 'Please accept the current terms.',
            'terms_version.in' => 'Please accept the current terms.',
        ]);
        if ($v->fails()) {
            $response = ['error' => 'invalid', 'message' => $v->errors()->first()];
            if ($requiredVersion !== null && $v->errors()->has('terms_version')) {
                $response['legal'] = Legal::consumerMetadata();
            }

            return response()->json($response, 422);
        }
        $user = DB::transaction(function () use ($data, $requiredVersion): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => User::ROLE_USER,
            ]);
            $user->forceFill(['username' => Username::suggest($user->name, $user)])->saveQuietly();
            if ($requiredVersion !== null) {
                Legal::record($user, Legal::SOURCE_APP, $requiredVersion);
            }
            // LinkHop's default folder, as the web sign-up made it before the split.
            if (Schema::hasTable('folders')) {
                DB::table('folders')->insert(['name' => 'Default', 'user_id' => $user->id, 'is_default' => true, 'created_at' => now(), 'updated_at' => now()]);
            }

            return $user;
        });
        // SMTP can take seconds per message: send both after the response.
        defer(function () use ($user) {
            event(new Registered($user)); // sends the "confirm your email" message
            try {
                Mail::to($user->email)->send(new WelcomeEmail($user, false));
            } catch (\Exception $e) {
                Log::error('Failed to send welcome email', ['error' => $e->getMessage(), 'user_id' => $user->id]);
            }
        });

        $response = ['user' => $this->payload($user, $hopApp)];
        if ($terms = $this->termsPayload($user)) {
            $response['terms'] = $terms;
        }

        return response()->json($response, 201);
    }

    /**
     * The acceptance recorded for this new account, or null while the legal
     * gate is inactive (key omitted entirely — existing consumers see the
     * exact response shape they saw before).
     *
     * @return array<string, string>|null
     */
    private function termsPayload(User $user): ?array
    {
        $acceptance = $user->legalAcceptances()->where('policy', Legal::POLICY_TERMS)->first();
        if (! $acceptance) {
            return null;
        }

        return [
            'policy' => $acceptance->policy,
            'version' => $acceptance->version,
            'sha256' => $acceptance->sha256,
            'source' => $acceptance->source,
            'accepted_at' => $acceptance->accepted_at->toIso8601String(),
        ];
    }

    /** The same facts the SSO ticket carries (v2 profile), plus `admin` for this app. */
    private function payload(User $user, HopApp $app): array
    {
        return $user->hopProfile() + ['admin' => $user->isAdminOf($app->slug)];
    }

    /** The visitor's IP as PixelHop saw it (signed with the body), or ''. */
    private function visitorIp(Request $request): string
    {
        $ip = (string) $request->json('ip', '');

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }

    /** The web's IP bans (CheckIpBan) apply to the visitor behind PixelHop too. */
    private function bannedIp(Request $request): ?JsonResponse
    {
        $ip = $this->visitorIp($request);
        $ban = $ip === '' || ! Schema::hasTable('ip_bans') ? null : DB::table('ip_bans')->where('ip_address', $ip)->first();
        if ($ban && ($ban->expires_at === null || now()->lessThanOrEqualTo($ban->expires_at))) {
            return response()->json(['error' => 'banned'], 403);
        }

        return null;
    }

    /** LinkHop's IP watchlist: count another attempt, or add the address. */
    private function watch(string $ip, int $userId, string $reason): void
    {
        if (! Schema::hasTable('ip_watchlist')) {
            return;
        }
        $row = DB::table('ip_watchlist')->where('ip_address', $ip)->first();
        if ($row) {
            DB::table('ip_watchlist')->where('id', $row->id)->update(['attempt_count' => DB::raw('attempt_count + 1'), 'last_attempt_at' => now(), 'updated_at' => now()]);
        } else {
            DB::table('ip_watchlist')->insert(['ip_address' => $ip, 'user_id' => $userId, 'reason' => $reason, 'last_attempt_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /**
     * New apps: HOP App Protocol headers (VerifyHopSignature already ran on
     * /api/apps/{app}/…). PixelHop 3.x: the older per-app signature. Either
     * way the app ends up in the request attributes.
     */
    private function verifySignature(Request $request, string $slug): ?JsonResponse
    {
        if ($hopApp = $request->attributes->get('hop_app')) {
            if ($hopApp->slug !== $slug || ! $hopApp->can('account_api')) {
                return response()->json(['error' => 'not_configured'], 404);
            }

            return null;
        }
        $hopApp = HopApp::find_($slug);
        if (! $hopApp || ! $hopApp->isConfigured() || ! $hopApp->can('account_api')) {
            return response()->json(['error' => 'not_configured'], 404);
        }
        $ts = (string) $request->header('X-PixelHop-Timestamp', '');
        $sig = (string) $request->header('X-PixelHop-Signature', '');
        if (! ctype_digit($ts) || abs(time() - (int) $ts) > self::MAX_AGE) {
            return response()->json(['error' => 'stale_request'], 401);
        }
        foreach ($hopApp->secrets() as $secret) {
            $expected = HopSignature::b64(hash_hmac('sha256', 'pixelhop-api|'.$ts.'|'.$request->getContent(), $secret, true));
            if (hash_equals($expected, $sig)) {
                $request->attributes->set('hop_app', $hopApp);

                return null;
            }
        }

        return response()->json(['error' => 'bad_signature'], 401);
    }
}
