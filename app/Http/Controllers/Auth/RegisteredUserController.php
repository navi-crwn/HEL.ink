<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeEmail;
use App\Models\User;
use App\Services\TurnstileService;
use App\Support\Legal;
use App\Support\Username;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

use function Illuminate\Support\defer;

class RegisteredUserController extends Controller
{
    public function __construct(private readonly TurnstileService $turnstile) {}

    public function create(): View
    {
        $requiredVersion = Legal::requiredVersion();
        Legal::assertUsable($requiredVersion);

        return view('auth.register', [
            'termsVersion' => $requiredVersion,
            'termsArtifact' => $requiredVersion === null ? null : Legal::artifact($requiredVersion),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $requiredVersion = Legal::requiredVersion();
        Legal::assertUsable($requiredVersion);
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'indisposable', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'tos_accepted' => ['required', 'accepted'],
            // While the gate is inactive no version is asked for; when active,
            // only the exact published version required right now is valid —
            // the client cannot name an arbitrary version.
            'terms_version' => $requiredVersion === null ? ['prohibited'] : ['required', 'in:'.$requiredVersion],
        ], [
            'email.indisposable' => 'Disposable email addresses are not allowed.',
            'terms_version.required' => 'Please accept the current terms.',
            'terms_version.in' => 'Please accept the current terms.',
        ]);
        if (! $this->turnstile->verify($request->input('cf-turnstile-response'), $request->ip())) {
            return back()
                ->withErrors(['turnstile' => 'Security verification failed, please try again.'])
                ->withInput($request->only('name', 'email'));
        }
        $user = DB::transaction(function () use ($request, $requiredVersion): User {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => User::ROLE_USER,
            ]);
            $user->forceFill(['username' => Username::suggest($user->name, $user)])->saveQuietly();
            if ($requiredVersion !== null) {
                Legal::record($user, Legal::SOURCE_WEB, $requiredVersion);
            }

            return $user;
        });
        Auth::login($user);
        // SMTP can take seconds per message: send both after the response.
        defer(function () use ($user) {
            event(new Registered($user)); // the "confirm your email" message
            try {
                \Mail::to($user->email)->send(new WelcomeEmail($user, false));
            } catch (\Exception $e) {
                \Log::error('Failed to send welcome email', [
                    'error' => $e->getMessage(),
                    'user_id' => $user->id,
                ]);
            }
        });

        return redirect()->route('verification.notice');
    }
}
