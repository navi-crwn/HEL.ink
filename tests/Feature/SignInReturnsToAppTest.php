<?php

use App\Models\HopApp;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Laravel\Socialite\Facades\Socialite;
use PragmaRX\Google2FA\Google2FA;

/*
 * A sign-in that an app started ends in that app: guest -> /sso/{app} -> sign-in page -> back to the
 * authorize URL -> back to the app with a ticket. Password, password plus two-factor, and Google.
 */

const RETURN_STATE = 'abcdefghijklmnop1234';

function authorizeUrlForPay(): string
{
    return '/sso/payhop?'.http_build_query([
        'return_to' => 'https://pay.example.com/auth/sso/callback',
        'state' => RETURN_STATE, // the intended URL keeps the query sorted by name
    ]);
}

function endsAtPayhopWithTicket($test, string $authorize): void
{
    $location = $test->get($authorize)->assertRedirect()->headers->get('Location');
    expect($location)->toStartWith('https://pay.example.com/auth/sso/callback?ticket=');
}

test('(a) password sign-in started by an app returns to the authorize URL and then to the app', function () {
    HopApp::factory()->payhop()->create();
    $user = User::factory()->create();

    $this->get(authorizeUrlForPay())->assertRedirect(route('login'));
    $back = $this->post('/login', ['email' => $user->email, 'password' => 'password'])->headers->get('Location');

    expect($back)->toEndWith(authorizeUrlForPay());
    endsAtPayhopWithTicket($this, $back);
});

test('(b) password plus two-factor: the intended URL survives the challenge and ends in the app', function () {
    HopApp::factory()->payhop()->create();
    $secret = (new Google2FA)->generateSecretKey();
    $user = User::factory()->create(['two_factor_secret' => Crypt::encrypt($secret), 'two_factor_confirmed_at' => now(),
        'two_factor_recovery_codes' => Crypt::encrypt(json_encode(['AAAAAAAAAA']))]);

    $this->get(authorizeUrlForPay())->assertRedirect(route('login'));
    $back = $this->post('/login', ['email' => $user->email, 'password' => 'password'])->headers->get('Location');
    expect($back)->toEndWith(authorizeUrlForPay());

    // The authorize step sees the open two-factor check and sends the person to the challenge.
    $this->get($back)->assertRedirect(route('two-factor.show'));
    $after = $this->post('/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp($secret)])->headers->get('Location');

    expect($after)->toEndWith(authorizeUrlForPay());
    endsAtPayhopWithTicket($this, $after);
});

test('(c) Google: the round trip returns to the authorize URL and then to the app', function () {
    $this->withoutVite();
    Queue::fake(); // linking the Google id queues a profile event to the apps
    config()->set('services.google.client_id', 'test-client');
    HopApp::factory()->payhop()->create();
    $user = User::factory()->create(['email' => 'g@example.com']);

    $this->get('/sso/payhop/google?'.http_build_query(['state' => RETURN_STATE, 'return_to' => 'https://pay.example.com/auth/sso/callback']))
        ->assertRedirect(route('auth.google', ['from_app' => 1]));

    $googleUser = Mockery::mock(Laravel\Socialite\Contracts\User::class);
    $googleUser->id = 'gid-1';
    $googleUser->email = $user->email;
    $googleUser->name = 'G';
    $googleUser->avatar = 'https://example.com/a.png';
    $googleUser->user = ['email_verified' => true];
    Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
    Socialite::shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
    Socialite::shouldReceive('user')->andReturn($googleUser);

    $this->get(route('auth.google', ['from_app' => 1]))->assertRedirect('https://accounts.google.com/o/oauth2/auth');
    $back = $this->get('/auth/google/callback')->assertRedirect()->headers->get('Location');

    $this->assertAuthenticated();
    expect($back)->toContain('/sso/payhop?')->toContain('state='.RETURN_STATE);
    endsAtPayhopWithTicket($this, $back);
});

test('already signed in at the hub: the authorize URL goes straight to the app', function () {
    HopApp::factory()->payhop()->create();
    $this->actingAs(User::factory()->create())->withSession(['2fa_verified' => true]);

    endsAtPayhopWithTicket($this, authorizeUrlForPay());
});

test('signing in at the hub itself (no app asked) lands on the dashboard', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard', absolute: false));
});
