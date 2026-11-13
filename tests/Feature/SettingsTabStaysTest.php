<?php

use App\Models\ApiToken;
use App\Models\User;

/**
 * Every form of Settings that can redirect after a result (done, refused, or a mistake in the form) must leave
 * the person on the tab the form is on: the redirect carries `active_tab`, which settings.blade.php reads.
 */
function settingsMember(array $attrs = []): User
{
    return User::factory()->create($attrs);
}

function withTwoFactor(): array
{
    return [
        'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_recovery_codes' => encrypt(json_encode(['AAAAAAAAAA'])),
        'two_factor_confirmed_at' => now(),
    ];
}

test('revoking a token leaves you on the API tab with the message', function () {
    $user = settingsMember();
    $token = ApiToken::generate($user->id, 'CLI')['model'];

    $this->actingAs($user)->withSession(['2fa_verified' => true])
        ->delete('/api-tokens/'.$token->id)
        ->assertRedirect()
        ->assertSessionHas('active_tab', 'api')
        ->assertSessionHas('status', 'Token revoked.');
    expect(ApiToken::find($token->id))->toBeNull();
});

test('creating a token without scripts shows the new token once, on the API tab', function () {
    $user = settingsMember();
    $this->actingAs($user)->withSession(['2fa_verified' => true])
        ->post('/api-tokens', ['name' => 'No script'])
        ->assertRedirect()
        ->assertSessionHas('active_tab', 'api')
        ->assertSessionHas('status', 'Token created.')
        ->assertSessionHas('api_token');
    $plain = session('api_token');

    $page = $this->withoutVite()->get('/settings')->assertOk();
    $page->assertSee("tab: 'api'", false);
    $page->assertSee($plain, false);   // the one-time token is on the page (no-script path)
    $this->withoutVite()->get('/settings')->assertDontSee($plain, false);   // and only once
});

test('a mistake in the token form leaves you on the API tab with the error', function () {
    $user = settingsMember();
    $this->actingAs($user)->withSession(['2fa_verified' => true])
        ->post('/api-tokens', ['name' => ''])
        ->assertRedirect()
        ->assertSessionHasErrors('name')
        ->assertSessionHas('active_tab', 'api');
    $this->actingAs($user)->withSession(['2fa_verified' => true])
        ->post('/api-tokens', ['name' => 'x', 'scopes' => ['root:*']])
        ->assertSessionHasErrors('scopes.0')
        ->assertSessionHas('active_tab', 'api');
});

test('a wrong password or a missing field in the two-factor forms keeps the two-factor tab', function () {
    $user = settingsMember();
    $this->actingAs($user)->withSession(['2fa_verified' => true]);

    $this->post('/settings/2fa/enable', ['password' => 'wrong-password'])->assertSessionHasErrors('password')->assertSessionHas('active_tab', '2fa');
    $this->post('/settings/2fa/enable', [])->assertSessionHasErrors('password')->assertSessionHas('active_tab', '2fa');

    $user->update(withTwoFactor());
    $this->post('/settings/2fa/disable', ['password' => 'wrong-password'])->assertSessionHasErrors('password')->assertSessionHas('active_tab', '2fa');
    $this->post('/settings/2fa/disable', [])->assertSessionHasErrors('password')->assertSessionHas('active_tab', '2fa');
    $this->post('/settings/2fa/recovery-codes', ['password' => 'wrong-password'])->assertSessionHasErrors('password')->assertSessionHas('active_tab', '2fa');
    $this->post('/settings/2fa/recovery-codes', [])->assertSessionHasErrors('password')->assertSessionHas('active_tab', '2fa');
    $this->post('/settings/2fa/confirm', [])->assertSessionHasErrors('code')->assertSessionHas('active_tab', '2fa');
});

test('forms on the Account tab still land on the Account tab', function () {
    $user = settingsMember();
    $this->actingAs($user)->withSession(['2fa_verified' => true]);
    $this->put('/password', ['current_password' => 'wrong', 'password' => 'a-new-Password-1', 'password_confirmation' => 'a-new-Password-1'])
        ->assertRedirect()->assertSessionMissing('active_tab');
    $this->patch('/profile', ['_back' => '/settings#account', 'name' => str_repeat('x', 300)])
        ->assertRedirect();
});

test('the language links of Settings keep the tab', function () {
    $user = settingsMember();
    $html = $this->withoutVite()->actingAs($user)->withSession(['2fa_verified' => true])->get('/settings')->assertOk()->getContent();
    expect($html)->toContain('hreflang="de"')->and($html)->toContain('keepTabOnLanguageLinks');
});
