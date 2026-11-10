<?php

namespace App\Http\Controllers;

use App\Support\AfterLogin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function enable(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);
        if (! Hash::check($request->password, $request->user()->password)) {
            return back()->withErrors(['password' => 'The provided password is incorrect.']);
        }
        // A confirmed setup is never overwritten: that would move 2FA to another authenticator without the old code.
        // An unconfirmed one may be started again. Turning it off (disable) or, for the owner, hop:reset-2fa comes first.
        if ($request->user()->two_factor_confirmed_at) {
            return back()->withErrors(['password' => __('Two-factor is already on for this account.')])->with('active_tab', '2fa');
        }
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();
        $recoveryCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $recoveryCodes[] = strtoupper(substr(md5(random_bytes(16)), 0, 10));
        }
        $request->user()->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        ]);

        return back()->with('success', 'Two-factor authentication has been enabled. Please scan the QR code.')->with('active_tab', '2fa');
    }

    public function confirm(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);
        $user = $request->user();
        $google2fa = new Google2FA;
        $secret = decrypt($user->two_factor_secret);
        $valid = $google2fa->verifyKey($secret, $request->code);
        if ($valid) {
            $user->update([
                'two_factor_confirmed_at' => now(),
            ]);

            return back()->with('success', 'Two-factor authentication has been confirmed successfully.')->with('active_tab', '2fa');
        }

        return back()->withErrors(['code' => 'The provided code is invalid.'])->with('active_tab', '2fa');
    }

    public function disable(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);
        if (! Hash::check($request->password, $request->user()->password)) {
            return back()->withErrors(['password' => 'The provided password is incorrect.']);
        }
        $request->user()->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        return back()->with('success', 'Two-factor authentication has been disabled.')->with('active_tab', '2fa');
    }

    public function regenerateRecoveryCodes(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);
        if (! Hash::check($request->password, $request->user()->password)) {
            return back()->withErrors(['password' => 'The provided password is incorrect.']);
        }
        $recoveryCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $recoveryCodes[] = strtoupper(substr(md5(random_bytes(16)), 0, 10));
        }
        $request->user()->update([
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        ]);

        return back()->with('success', 'Recovery codes have been regenerated.')->with('active_tab', '2fa');
    }

    public function show()
    {
        return view('auth.two-factor-challenge');
    }

    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);
        $user = $request->user();
        $google2fa = new Google2FA;
        $secret = decrypt($user->two_factor_secret);
        $valid = $google2fa->verifyKey($secret, $request->code);
        // A TOTP code is good for 3 time steps (90 s with the default window): remember it so it cannot open a second session.
        if ($valid && ! Cache::add("2fa-used:{$user->id}:{$request->code}", true, 120)) {
            return back()->withErrors(['code' => 'The provided code is invalid.']);
        }
        if (! $valid) {
            $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);
            $valid = in_array(strtoupper($request->code), $recoveryCodes, true);
            if ($valid) {
                $recoveryCodes = array_values(array_diff($recoveryCodes, [strtoupper($request->code)]));
                $user->update([
                    'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
                ]);
            }
        }
        if ($valid) {
            $request->session()->put('2fa_verified', true);

            return redirect()->intended(AfterLogin::home($request->user()));
        }

        return back()->withErrors(['code' => 'The provided code is invalid.']);
    }
}
