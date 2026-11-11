<?php

use App\Mail\EmailChangedNotice;
use App\Models\ApiToken;
use App\Models\HopApp;
use App\Models\User;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/*
 * The attacks around the Google link: the account's address is proved only by Google presenting that same
 * address, and a Google link never stands for a verified address.
 */

function gcSession(User $user): void
{
    DB::table('sessions')->insert(['id' => Str::random(40), 'user_id' => $user->id, 'payload' => 'x', 'last_activity' => time()]);
}

function gcGoogle(string $email, string $gid): void
{
    $g = Mockery::mock(Laravel\Socialite\Contracts\User::class);
    $g->id = $gid;
    $g->email = $email;
    $g->name = 'Google Person';
    $g->avatar = 'https://example.com/a.png';
    $g->user = ['email_verified' => true];
    Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
    Socialite::shouldReceive('user')->andReturn($g);
}

beforeEach(function () {
    Queue::fake();
    HopApp::factory()->payhop()->create();
});

// ---- rows 1 and 2 ---------------------------------------------------------------

test('(1) a Google link alone never verifies the address the account holds: the attacker who moved the e-mail stays unverified', function () {
    // The attacker signed up through Google, then put the victim's address on the account.
    $attacker = User::factory()->unverified()->create(['email' => 'victim@example.com', 'google_id' => 'G-ATT']);
    DB::table('users')->where('id', $attacker->id)->update(['email_verified_at' => null]);

    gcGoogle('attacker@gmail.test', 'G-ATT'); // Google presents the attacker's own address
    $this->get('/auth/google/callback')->assertRedirect();

    $attacker = $attacker->fresh();
    expect($attacker->hasVerifiedEmail())->toBeFalse()->and($attacker->email)->toBe('victim@example.com');
    // And the apps do not get a ticket for an unverified address.
    $this->get('/sso/payhop?'.http_build_query(['return_to' => 'https://pay.example.com/auth/sso/callback', 'state' => 'abcdefghijklmnop1234']))
        ->assertRedirect(route('verification.notice'));
    expect($attacker->hopProfile()['verified'])->toBeFalse();
});

test('(1) when the real owner then comes with Google, the account is claimed and the attacker loses the Google link and the rest', function () {
    $attacker = User::factory()->unverified()->create(['email' => 'victim@example.com', 'google_id' => 'G-ATT', 'password' => Hash::make('attacker-pass-123')]);
    $attacker->forceFill(['two_factor_secret' => Crypt::encrypt('S'), 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => Crypt::encrypt('[]')])->save();
    gcSession($attacker);
    ApiToken::generate($attacker->id, 'attacker');

    gcGoogle('victim@example.com', 'G-VIC');
    $this->get('/auth/google/callback')->assertRedirect();

    $account = $attacker->fresh();
    expect($this->isAuthenticated())->toBeTrue()->and(auth()->id())->toBe($account->id)
        ->and($account->hasVerifiedEmail())->toBeTrue()
        ->and($account->google_id)->toBe('G-VIC')
        ->and(Hash::check('attacker-pass-123', $account->password))->toBeFalse()
        ->and($account->two_factor_secret)->toBeNull()->and($account->apiTokens()->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $account->id)->count())->toBe(0);

    // The attacker's Google no longer opens it.
    $this->post('/logout');
    gcGoogle('attacker@gmail.test', 'G-ATT');
    $this->get('/auth/google/callback');
    expect($account->fresh()->google_id)->toBe('G-VIC')->and(User::where('google_id', 'G-ATT')->where('id', $account->id)->exists())->toBeFalse();
});

test('(2) claiming an unverified account whose Google link is another Google account replaces the link', function () {
    $acct = User::factory()->unverified()->create(['email' => 'victim2@example.com', 'google_id' => 'G-OTHER']);
    DB::table('users')->where('id', $acct->id)->update(['email_verified_at' => null]);

    gcGoogle('victim2@example.com', 'G-REAL');
    $this->get('/auth/google/callback')->assertRedirect();

    expect($acct->fresh()->google_id)->toBe('G-REAL')->and($acct->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('(2) a verified account whose Google link differs from the presenting Google account is not relinked, and nobody is signed in', function () {
    $acct = User::factory()->create(['email' => 'verified@example.com', 'google_id' => 'G-FIRST']);

    gcGoogle('verified@example.com', 'G-SECOND');
    $this->get('/auth/google/callback')->assertRedirect(route('login'))->assertSessionHas('error');

    $this->assertGuest();
    expect($acct->fresh()->google_id)->toBe('G-FIRST');
});

test('(2) an account found by e-mail and a different account that holds the Google link: the e-mail account is the one, and the link moves', function () {
    $byEmail = User::factory()->create(['email' => 'mine@example.com', 'google_id' => null]);
    $stale = User::factory()->create(['email' => 'moved-away@example.com', 'google_id' => 'G-ME']);

    gcGoogle('mine@example.com', 'G-ME');
    $this->get('/auth/google/callback')->assertRedirect();

    expect(auth()->id())->toBe($byEmail->id)->and($byEmail->fresh()->google_id)->toBe('G-ME')->and($stale->fresh()->google_id)->toBeNull();
});

test('a match by the Google link alone is an ordinary sign-in and never changes the e-mail status', function () {
    $acct = User::factory()->create(['email' => 'old-address@example.com', 'google_id' => 'G-LEGACY']);
    $hash = $acct->password;

    gcGoogle('new-google-address@example.com', 'G-LEGACY');
    $this->get('/auth/google/callback')->assertRedirect();

    expect(auth()->id())->toBe($acct->id)->and($acct->fresh()->email)->toBe('old-address@example.com')
        ->and($acct->fresh()->password)->toBe($hash)->and($acct->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('an account already linked to this Google account only gets verified, nothing else is cleared', function () {
    $acct = User::factory()->unverified()->create(['email' => 'legacy@example.com', 'google_id' => 'G-LEG']);
    DB::table('users')->where('id', $acct->id)->update(['email_verified_at' => null]);
    $acct->forceFill(['two_factor_secret' => Crypt::encrypt('S'), 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => Crypt::encrypt('[]')])->save();
    $hash = $acct->fresh()->password;

    gcGoogle('legacy@example.com', 'G-LEG');
    $this->get('/auth/google/callback')->assertRedirect();

    expect($acct->fresh()->hasVerifiedEmail())->toBeTrue()->and($acct->fresh()->two_factor_secret)->not->toBeNull()->and($acct->fresh()->password)->toBe($hash);
});

test('the address is matched without regard to case', function () {
    $acct = User::factory()->unverified()->create(['email' => 'mixed@example.com']);
    DB::table('users')->where('id', $acct->id)->update(['email_verified_at' => null]);

    gcGoogle('Mixed@Example.COM', 'G-MIX');
    $this->get('/auth/google/callback')->assertRedirect();

    expect(auth()->id())->toBe($acct->id)->and($acct->fresh()->hasVerifiedEmail())->toBeTrue();
});

// ---- row 3 -------------------------------------------------------------------------

test('(3) a password reset of an unverified account that has a Google link also drops the link, the two-factor and the tokens', function () {
    $acct = User::factory()->unverified()->create(['google_id' => 'G-ATT']);
    DB::table('users')->where('id', $acct->id)->update(['email_verified_at' => null]);
    $acct->forceFill(['two_factor_secret' => Crypt::encrypt('S'), 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => Crypt::encrypt('[]')])->save();
    ApiToken::generate($acct->id, 'attacker');
    gcSession($acct);

    $this->post('/reset-password', ['token' => Password::createToken($acct), 'email' => $acct->email, 'password' => 'Fresh-reset-pass-789', 'password_confirmation' => 'Fresh-reset-pass-789'])
        ->assertRedirect(route('login'));

    $acct = $acct->fresh();
    expect($acct->google_id)->toBeNull()->and($acct->two_factor_secret)->toBeNull()->and($acct->apiTokens()->count())->toBe(0)
        ->and($acct->hasVerifiedEmail())->toBeTrue()->and(DB::table('sessions')->where('user_id', $acct->id)->count())->toBe(0);
});

// ---- row 4 -------------------------------------------------------------------------

test('(4) an unverified account with a Google link gets no SSO ticket and no app sign-in, and is not presented as verified', function () {
    $acct = User::factory()->unverified()->create(['google_id' => 'G-X']);
    DB::table('users')->where('id', $acct->id)->update(['email_verified_at' => null]);

    $this->actingAs($acct)->withSession(['2fa_verified' => true])
        ->get('/sso/payhop?'.http_build_query(['return_to' => 'https://pay.example.com/auth/sso/callback', 'state' => 'abcdefghijklmnop1234']))
        ->assertRedirect(route('verification.notice'));
    expect($acct->hopProfile()['verified'])->toBeFalse();
});

test('(4) an honest account created through Google is verified at creation and gets its ticket', function () {
    gcGoogle('fresh-google@example.com', 'G-NEW');
    $this->get('/auth/google/callback')->assertRedirect();

    $acct = User::where('email', 'fresh-google@example.com')->firstOrFail();
    expect($acct->hasVerifiedEmail())->toBeTrue()->and($acct->hopProfile()['verified'])->toBeTrue();
    $this->withSession(['2fa_verified' => true])
        ->get('/sso/payhop?'.http_build_query(['return_to' => 'https://pay.example.com/auth/sso/callback', 'state' => 'abcdefghijklmnop1234']))
        ->assertRedirect();
});

// ---- row 5 -------------------------------------------------------------------------

test('(5) changing the e-mail needs the current password, drops the Google link, tells the old address and ends other sessions', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => 'before@example.com', 'username' => 'changer', 'google_id' => 'G-OLD', 'password' => Hash::make('right-password-1')]);
    gcSession($user);

    $base = ['name' => $user->name, 'username' => 'changer', 'email' => 'after@example.com'];
    $this->actingAs($user)->withSession(['2fa_verified' => true])->patch('/profile', $base)->assertSessionHasErrors('current_password');
    $this->actingAs($user)->withSession(['2fa_verified' => true])->patch('/profile', $base + ['current_password' => 'wrong'])->assertSessionHasErrors('current_password');
    expect($user->fresh()->email)->toBe('before@example.com');

    $this->actingAs($user)->withSession(['2fa_verified' => true])->patch('/profile', $base + ['current_password' => 'right-password-1'])->assertSessionHasNoErrors();

    $user = $user->fresh();
    expect($user->email)->toBe('after@example.com')->and($user->google_id)->toBeNull()->and($user->hasVerifiedEmail())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
    app(DeferredCallbackCollection::class)->invoke();
    Mail::assertSent(EmailChangedNotice::class, fn ($m) => $m->hasTo('before@example.com') && $m->newEmail === 'after@example.com');
});

test('(5) changing only the name needs no password and sends no notice', function () {
    Mail::fake();
    $user = User::factory()->create(['username' => 'plain']);

    $this->actingAs($user)->withSession(['2fa_verified' => true])->patch('/profile', ['name' => 'Another Name', 'username' => 'plain', 'email' => $user->email])->assertSessionHasNoErrors();

    app(DeferredCallbackCollection::class)->invoke();
    expect($user->fresh()->name)->toBe('Another Name');
    Mail::assertNothingSent();
});

test('(5) a Google-only account without a password gets a hint, not a changed address', function () {
    $user = User::factory()->create(['email' => 'g-only@example.com', 'username' => 'gonly', 'google_id' => 'G-1', 'password' => Hash::make(Str::random(40))]);

    $this->actingAs($user)->withSession(['2fa_verified' => true])->patch('/profile', ['name' => $user->name, 'username' => 'gonly', 'email' => 'other@example.com', 'current_password' => 'guess'])
        ->assertSessionHasErrors('current_password');

    expect($user->fresh()->email)->toBe('g-only@example.com')->and($user->fresh()->google_id)->toBe('G-1');
});

// ---- row 6 -------------------------------------------------------------------------

test('(6) setting a password from onboarding needs the sign-up session marker and then ends the other sessions', function () {
    $user = User::factory()->create(['google_id' => 'G-ON']);
    gcSession($user);

    // An ordinary session of a Google account: refused, nothing changes.
    $hash = $user->password;
    $this->actingAs($user)->withSession(['2fa_verified' => true])->post('/onboarding/set-password', ['password' => 'a-new-password-1', 'password_confirmation' => 'a-new-password-1'])
        ->assertSessionHasErrors('password');
    expect($user->fresh()->password)->toBe($hash)->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(1);

    // The session of the sign-up: allowed, and everything else of the account ends.
    $this->actingAs($user)->withSession(['2fa_verified' => true, 'google_user_needs_password' => true])
        ->post('/onboarding/set-password', ['password' => 'a-new-password-1', 'password_confirmation' => 'a-new-password-1'])->assertSessionHasNoErrors();
    expect(Hash::check('a-new-password-1', $user->fresh()->password))->toBeTrue()->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
});

// ---- row 7 -------------------------------------------------------------------------

test('(7) the old-password comparison happens only after the token is valid', function () {
    $user = User::factory()->create(['password' => Hash::make('current-password-1')]);

    // A guess of the current password with no valid token: the answer is the token error, never "do not reuse".
    $res = $this->from('/forgot-password')->post('/reset-password', ['token' => 'not-a-real-token', 'email' => $user->email, 'password' => 'current-password-1', 'password_confirmation' => 'current-password-1']);
    $res->assertSessionHasErrors('email');
    expect(session('errors')->has('password'))->toBeFalse();

    // With a valid token the old password is still refused, and the token stays usable.
    $token = Password::createToken($user);
    $this->from('/forgot-password')->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'current-password-1', 'password_confirmation' => 'current-password-1'])
        ->assertSessionHasErrors('password');
    $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'Another-fresh-pass-22', 'password_confirmation' => 'Another-fresh-pass-22'])
        ->assertRedirect(route('login'));
});

test('(5) the settings page, where the profile is edited, has the current-password field for an e-mail change', function () {
    $user = User::factory()->create();

    $this->withoutVite()->actingAs($user)->get('/settings')->assertOk()
        ->assertSee('name="current_password"', false)
        ->assertSee('Current password (only to change your email)');
});
