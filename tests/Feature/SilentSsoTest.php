<?php

use App\Models\HopApp;
use App\Models\User;

const SILENT_STATE = 'state-state-state-1234';

function silentUrl(array $extra = [], string $app = 'filehop'): string
{
    return '/sso/'.$app.'?'.http_build_query(['state' => SILENT_STATE, 'return_to' => 'https://file.example.com/auth/sso/callback'] + $extra);
}

beforeEach(function () {
    HopApp::factory()->filehop()->create();
});

test('prompt=none with a hub session issues the normal ticket', function () {
    $user = User::factory()->create();
    $res = $this->actingAs($user)->withSession(['2fa_verified' => true])->get(silentUrl(['prompt' => 'none']));
    expect($res->headers->get('Location'))->toStartWith('https://file.example.com/auth/sso/callback?ticket=');
    expect($res->headers->get('Location'))->not->toContain('error=');
});

test('prompt=none without a hub session answers login_required at the registered callback and keeps nothing', function () {
    $res = $this->get(silentUrl(['prompt' => 'none']));
    expect($res->headers->get('Location'))->toBe('https://file.example.com/auth/sso/callback?error=login_required&state='.SILENT_STATE);
    $this->assertGuest();
    expect(session('url.intended'))->toBeNull();
});

test('prompt=none for a suspended account or an open two-factor check answers login_required', function () {
    $suspended = User::factory()->create(['status' => User::STATUS_SUSPENDED]);
    $res = $this->actingAs($suspended)->withSession(['2fa_verified' => true])->get(silentUrl(['prompt' => 'none']));
    expect($res->headers->get('Location'))->toBe('https://file.example.com/auth/sso/callback?error=login_required&state='.SILENT_STATE);

    $twoFactor = User::factory()->create(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now()]);
    $res = $this->actingAs($twoFactor)->get(silentUrl(['prompt' => 'none']));
    expect($res->headers->get('Location'))->toContain('error=login_required')->not->toContain('ticket=');
});

test('the target is the registered callback: another return_to is refused, an unknown app is 404', function () {
    $bad = '/sso/filehop?'.http_build_query(['state' => SILENT_STATE, 'prompt' => 'none', 'return_to' => 'https://evil.example/cb']);
    $this->get($bad)->assertStatus(400);
    $this->get('/sso/filehop?'.http_build_query(['state' => SILENT_STATE, 'prompt' => 'none', 'return_to' => '//evil.example']))->assertStatus(400);
    $this->get(silentUrl(['prompt' => 'none'], 'nosuchapp'))->assertNotFound();
});

test('without prompt=none a guest is sent to sign in and returns to the ticket step, as before', function () {
    $res = $this->get(silentUrl());
    $res->assertRedirect(route('login'));
    expect(session('url.intended'))->toContain('/sso/filehop');
    $this->get(silentUrl(['prompt' => 'login']))->assertRedirect(route('login'));
});
