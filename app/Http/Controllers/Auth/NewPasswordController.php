<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Input;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function create(Request $request): View
    {
        if ($request->user()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return view('auth.reset-password', ['request' => $request]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Rate limiting: 5 attempts per hour per email
        $key = 'password-reset:'.strtolower(Input::text($request->email));
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => sprintf('Too many password reset attempts. Please try again in %d minutes.', ceil($seconds / 60)),
            ]);
        }
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);
        $user = User::where('email', $request->email)->first();
        // Check if new password is same as old password
        if ($user && Hash::check($request->password, $user->password)) {
            RateLimiter::hit($key, 3600);
            throw ValidationException::withMessages([
                'password' => 'Please choose a new password. Do not use your old password for security reasons.',
            ]);
        }
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request, $key) {
                abort_if($user->isSuperAdmin(), 403, 'Superadmin accounts are permanent and read-only.');
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();
                // The person is not signed in here, so the row is written directly (ActivityLog::log needs a session).
                ActivityLog::create([
                    'user_id' => $user->id,
                    'action' => 'updated',
                    'model' => 'Password',
                    'model_id' => $user->id,
                    'description' => 'Reset password by email',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
                event(new PasswordReset($user));
                // Clear rate limiter on successful reset
                RateLimiter::clear($key);
            }
        );
        // Hit rate limiter on failure
        if ($status !== Password::PASSWORD_RESET) {
            RateLimiter::hit($key, 3600);
        }

        return $status == Password::PASSWORD_RESET
                    ? redirect()->route('login')->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
