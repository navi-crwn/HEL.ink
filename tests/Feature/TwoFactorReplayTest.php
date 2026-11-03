<?php

use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

function twoFactorUser(string $secret): User
{
    return User::factory()->create([
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['AAAAAAAAAA'])),
        'two_factor_confirmed_at' => now(),
    ]);
}

test('a TOTP code that already opened one session is refused in a second session inside its window', function () {
    $google2fa = new Google2FA;
    $secret = $google2fa->generateSecretKey();
    $user = twoFactorUser($secret);
    $code = $google2fa->getCurrentOtp($secret);

    $this->actingAs($user)->post('/two-factor-challenge', ['code' => $code])
        ->assertSessionHasNoErrors()->assertSessionHas('2fa_verified', true);

    $this->flushSession();
    $this->actingAs($user)->post('/two-factor-challenge', ['code' => $code])
        ->assertSessionHasErrors('code')->assertSessionMissing('2fa_verified');
});

test('a fresh TOTP code is still accepted after a used one was refused', function () {
    $google2fa = new Google2FA;
    $secret = $google2fa->generateSecretKey();
    $user = twoFactorUser($secret);
    $used = $google2fa->getCurrentOtp($secret);
    $next = $google2fa->oathTotp($secret, $google2fa->getTimestamp() + 1);

    $this->actingAs($user)->post('/two-factor-challenge', ['code' => $used])->assertSessionHas('2fa_verified', true);
    $this->flushSession();
    $this->actingAs($user)->post('/two-factor-challenge', ['code' => $used])->assertSessionHasErrors('code');
    $this->actingAs($user)->post('/two-factor-challenge', ['code' => $next])
        ->assertSessionHasNoErrors()->assertSessionHas('2fa_verified', true);
});

test('the used-code memory is per user', function () {
    $google2fa = new Google2FA;
    $secret = $google2fa->generateSecretKey();
    $first = twoFactorUser($secret);
    $second = twoFactorUser($secret);
    $code = $google2fa->getCurrentOtp($secret);

    $this->actingAs($first)->post('/two-factor-challenge', ['code' => $code])->assertSessionHas('2fa_verified', true);
    $this->flushSession();
    $this->actingAs($second)->post('/two-factor-challenge', ['code' => $code])->assertSessionHas('2fa_verified', true);
});

test('recovery codes still work once', function () {
    $google2fa = new Google2FA;
    $user = twoFactorUser($google2fa->generateSecretKey());

    $this->actingAs($user)->post('/two-factor-challenge', ['code' => 'aaaaaaaaaa'])->assertSessionHas('2fa_verified', true);
    $this->flushSession();
    $this->actingAs($user)->post('/two-factor-challenge', ['code' => 'AAAAAAAAAA'])->assertSessionHasErrors('code');
});
