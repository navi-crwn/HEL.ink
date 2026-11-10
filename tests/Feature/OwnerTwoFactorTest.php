<?php

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

function ownerAccount(array $attrs = []): User
{
    $owner = User::factory()->create($attrs);
    $owner->roles()->create(['role' => Role::OWNER, 'app_slug' => null]);

    return $owner;
}

test('the owner can turn on, confirm and renew recovery codes for their own 2FA', function () {
    $owner = ownerAccount();
    $this->actingAs($owner)->withSession(['2fa_verified' => true]);

    $this->post('/settings/2fa/enable', ['password' => 'password'])->assertRedirect();
    $owner = $owner->fresh();
    expect($owner->two_factor_secret)->not->toBeNull();
    expect($owner->two_factor_confirmed_at)->toBeNull();

    $code = (new Google2FA)->getCurrentOtp(decrypt($owner->two_factor_secret));
    $this->post('/settings/2fa/confirm', ['code' => $code])->assertSessionHasNoErrors();
    expect($owner->fresh()->hasTwoFactor())->toBeTrue();

    $before = $owner->fresh()->two_factor_recovery_codes;
    $this->post('/settings/2fa/recovery-codes', ['password' => 'password'])->assertSessionHasNoErrors();
    expect($owner->fresh()->two_factor_recovery_codes)->not->toBe($before);
});

test('the owner cannot turn 2FA off and the account stays protected', function () {
    $owner = ownerAccount([
        'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_recovery_codes' => encrypt(json_encode(['AAAAAAAAAA'])),
        'two_factor_confirmed_at' => now(),
    ]);
    $this->actingAs($owner)->withSession(['2fa_verified' => true]);
    $this->deleteJson('/settings/2fa/disable', ['password' => 'password'])->assertForbidden();
    expect($owner->fresh()->hasTwoFactor())->toBeTrue();
    $this->get('/settings')->assertOk()->assertSee('Recovery codes')->assertDontSee('Disable 2FA');
});

test('the owner 2FA tab shows the normal flow when 2FA is off', function () {
    $owner = ownerAccount();
    $this->actingAs($owner)->withSession(['2fa_verified' => true]);
    $this->get('/settings')->assertOk()->assertSee('Turn on two-factor')->assertSee(route('settings.2fa.enable'), false);
});

test('another user or an admin cannot touch the owner 2FA', function () {
    $owner = ownerAccount([
        'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_recovery_codes' => encrypt(json_encode(['AAAAAAAAAA'])),
        'two_factor_confirmed_at' => now(),
    ]);
    $admin = User::factory()->create();
    $admin->roles()->create(['role' => Role::ADMIN, 'app_slug' => null]);
    foreach ([User::factory()->create(), $admin] as $actor) {
        $this->actingAs($actor)->withSession(['2fa_verified' => true]);
        foreach (['suspend', 'activate', 'role'] as $action) {
            $this->postJson('/admin/users/'.$owner->id.'/'.$action)->assertForbidden();
        }
        $this->deleteJson('/admin/users/'.$owner->id)->assertForbidden();
        // The 2FA routes only ever act on the signed-in account, never on the owner.
        $this->post('/settings/2fa/enable', ['password' => 'password']);
        $this->post('/settings/2fa/recovery-codes', ['password' => 'password']);
    }
    $fresh = $owner->fresh();
    expect($fresh->two_factor_secret)->toBe($owner->two_factor_secret);
    expect($fresh->two_factor_recovery_codes)->toBe($owner->two_factor_recovery_codes);
    expect($fresh->two_factor_confirmed_at)->not->toBeNull();
});

test('hop:reset-2fa clears the three columns, logs without secrets and refuses unknown emails', function () {
    $owner = ownerAccount([
        'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_recovery_codes' => encrypt(json_encode(['AAAAAAAAAA'])),
        'two_factor_confirmed_at' => now(),
    ]);
    $this->artisan('hop:reset-2fa', ['email' => $owner->email])->assertSuccessful()
        ->doesntExpectOutputToContain('JBSWY3DPEHPK3PXP')->doesntExpectOutputToContain('AAAAAAAAAA');
    $fresh = $owner->fresh();
    expect($fresh->two_factor_secret)->toBeNull();
    expect($fresh->two_factor_recovery_codes)->toBeNull();
    expect($fresh->two_factor_confirmed_at)->toBeNull();
    $log = ActivityLog::where('user_id', $owner->id)->where('action', 'two_factor_reset')->first();
    expect($log)->not->toBeNull();
    expect($log->description)->not->toContain('JBSWY3DPEHPK3PXP');

    $this->artisan('hop:reset-2fa', ['email' => 'nobody@example.test'])->assertFailed();
    expect(ActivityLog::where('action', 'two_factor_reset')->count())->toBe(1);
});
