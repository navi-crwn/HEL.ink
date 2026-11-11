<?php

use App\Jobs\SendHopEvent;
use App\Models\ActivityLog;
use App\Models\ApiToken;
use App\Models\HopApp;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountSessions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/*
 * Ending what an account can still use elsewhere: a Google claim of an unverified sign-up, password change and reset,
 * two-factor off, e-mail change, and suspension.
 */

function otherSession(User $user): string
{
    $id = Str::random(40);
    DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'payload' => 'x', 'last_activity' => time()]);

    return $id;
}

function sessionCount(User $user): int
{
    return DB::table('sessions')->where('user_id', $user->id)->count();
}

function eventApps(): void
{
    HopApp::factory()->payhop()->create();
    HopApp::factory()->filehop()->create();
}

function revokedEvents(): int
{
    return count(Queue::pushed(SendHopEvent::class, fn ($j) => $j->type === 'session.revoked'));
}

function googleReturns(string $email): void
{
    $g = Mockery::mock(Laravel\Socialite\Contracts\User::class);
    $g->id = 'gid-'.Str::random(6);
    $g->email = $email;
    $g->name = 'Real Owner';
    $g->avatar = 'https://example.com/a.png';
    $g->user = ['email_verified' => true];
    Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
    Socialite::shouldReceive('user')->andReturn($g);
}

beforeEach(function () {
    Queue::fake();
    eventApps();
});

// ---- the pre-hijack scenario ----------------------------------------------------

test('a Google claim of an unverified sign-up ends everything the first registrant could use', function () {
    // Someone signs up with the victim's address and stays signed in.
    $this->post('/register', ['name' => 'Early Bird', 'email' => 'victim@example.com', 'password' => 'attacker-pass-123', 'password_confirmation' => 'attacker-pass-123', 'tos_accepted' => '1'])
        ->assertRedirect(route('verification.notice'));
    $user = User::where('email', 'victim@example.com')->firstOrFail();
    expect($user->hasVerifiedEmail())->toBeFalse();
    otherSession($user);
    ApiToken::generate($user->id, 'attacker token');
    $user->forceFill(['two_factor_secret' => Crypt::encrypt('SECRET'), 'two_factor_recovery_codes' => Crypt::encrypt('[]'), 'two_factor_confirmed_at' => now(), 'remember_token' => 'oldremember'])->save();
    $this->post('/logout');
    $this->flushSession();
    Queue::fake();

    // The real owner comes in through Google.
    googleReturns('victim@example.com');
    $this->get('/auth/google/callback')->assertRedirect();

    $user = $user->fresh();
    expect($this->isAuthenticated())->toBeTrue()
        ->and(auth()->id())->toBe($user->id)
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('attacker-pass-123', $user->password))->toBeFalse()
        ->and($user->remember_token)->not->toBe('oldremember')
        ->and($user->two_factor_secret)->toBeNull()->and($user->two_factor_recovery_codes)->toBeNull()->and($user->two_factor_confirmed_at)->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and($user->apiTokens()->count())->toBe(0)
        ->and(ActivityLog::where('user_id', $user->id)->where('description', 'like', 'Claimed through Google%')->count())->toBe(1);
    expect(revokedEvents())->toBe(2);

    // And the first registrant's password no longer opens the account.
    $this->post('/logout');
    $this->post('/login', ['email' => 'victim@example.com', 'password' => 'attacker-pass-123']);
    $this->assertGuest();
});

test('an account whose address was already verified is left exactly as it was', function () {
    $user = User::factory()->create(['email' => 'known@example.com']);
    $hash = $user->password;
    otherSession($user);
    ApiToken::generate($user->id, 'mine');
    $user->forceFill(['remember_token' => 'keepme', 'two_factor_secret' => Crypt::encrypt('S'), 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => Crypt::encrypt('[]')])->save();

    googleReturns('known@example.com');
    $this->get('/auth/google/callback')->assertRedirect();

    $user = $user->fresh();
    expect($user->password)->toBe($hash)->and($user->remember_token)->toBe('keepme')->and($user->two_factor_secret)->not->toBeNull()
        ->and(sessionCount($user))->toBe(1)->and($user->apiTokens()->count())->toBe(1);
    expect(revokedEvents())->toBe(0);
});

// ---- password change, reset, two-factor off, e-mail change --------------------------

test('changing the password ends the other sessions, rotates the remember token and tells the apps once per app', function () {
    $user = User::factory()->create(['password' => Hash::make('old-password-123')]);
    $user->forceFill(['remember_token' => 'oldremember'])->save();
    otherSession($user);
    otherSession($user);

    $this->actingAs($user)->withSession(['2fa_verified' => true])
        ->put('/password', ['current_password' => 'old-password-123', 'password' => 'brand-new-pass-456', 'password_confirmation' => 'brand-new-pass-456'])
        ->assertSessionHasNoErrors();

    expect(sessionCount($user))->toBe(0)
        ->and($user->fresh()->remember_token)->not->toBe('oldremember');
    expect(revokedEvents())->toBe(2);
});

test('a password reset by e-mail ends every session of a verified account and keeps its two-factor', function () {
    $user = User::factory()->create();
    $user->forceFill(['remember_token' => 'old', 'two_factor_secret' => Crypt::encrypt('S'), 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => Crypt::encrypt('[]')])->save();
    otherSession($user);
    otherSession($user);
    ApiToken::generate($user->id, 'mine');

    $this->post('/reset-password', ['token' => Password::createToken($user), 'email' => $user->email, 'password' => 'Fresh-reset-pass-789', 'password_confirmation' => 'Fresh-reset-pass-789'])
        ->assertRedirect(route('login'));

    $user = $user->fresh();
    expect(sessionCount($user))->toBe(0)->and($user->remember_token)->not->toBe('old')
        ->and($user->two_factor_secret)->not->toBeNull()->and($user->apiTokens()->count())->toBe(1);
    expect(revokedEvents())->toBe(2);
});

test('a password reset of an unverified sign-up also removes its two-factor and tokens and verifies the address', function () {
    $user = User::factory()->unverified()->create();
    $user->forceFill(['two_factor_secret' => Crypt::encrypt('S'), 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => Crypt::encrypt('[]')])->save();
    otherSession($user);
    ApiToken::generate($user->id, 'attacker');

    $this->post('/reset-password', ['token' => Password::createToken($user), 'email' => $user->email, 'password' => 'Fresh-reset-pass-789', 'password_confirmation' => 'Fresh-reset-pass-789'])
        ->assertRedirect(route('login'));

    $user = $user->fresh();
    expect(sessionCount($user))->toBe(0)->and($user->two_factor_secret)->toBeNull()->and($user->apiTokens()->count())->toBe(0)
        ->and($user->hasVerifiedEmail())->toBeTrue();
});

test('turning two-factor off ends the other sessions and the apps follow', function () {
    $user = User::factory()->create(['password' => Hash::make('the-password-123')]);
    $user->forceFill(['remember_token' => 'old', 'two_factor_secret' => Crypt::encrypt('S'), 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => Crypt::encrypt('[]')])->save();
    otherSession($user);

    $this->actingAs($user)->withSession(['2fa_verified' => true])->delete('/settings/2fa/disable', ['password' => 'the-password-123'])->assertSessionHasNoErrors();

    expect($user->fresh()->two_factor_secret)->toBeNull()
        ->and(sessionCount($user))->toBe(0)
        ->and($user->fresh()->remember_token)->not->toBe('old');
    expect(revokedEvents())->toBe(2);
});

test('changing the e-mail address ends the other sessions', function () {
    $user = User::factory()->create(['email' => 'before@example.com', 'username' => 'tester1']);
    otherSession($user);

    $this->actingAs($user)->withSession(['2fa_verified' => true])->patch('/profile', ['name' => $user->name, 'email' => 'after@example.com', 'username' => $user->username])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->email)->toBe('after@example.com')
        ->and(sessionCount($user))->toBe(0);
    expect(revokedEvents())->toBe(2);

    // A change of the name alone does not touch sessions.
    otherSession($user);
    $this->actingAs($user->fresh())->withSession(['2fa_verified' => true])->patch('/profile', ['name' => 'New Name', 'email' => 'after@example.com', 'username' => $user->username]);
    expect(sessionCount($user))->toBe(1);
});

// ---- suspension -----------------------------------------------------------------

test('suspending through the old admin page ends the sessions and tokens and tells the apps', function () {
    $admin = User::factory()->create();
    $admin->roles()->create(['role' => Role::ADMIN, 'app_slug' => null]);
    $target = User::factory()->create();
    otherSession($target);
    otherSession($target);
    ApiToken::generate($target->id, 'x');

    $res = $this->actingAs($admin)->withSession(['2fa_verified' => true])->post('/admin/users/'.$target->id.'/suspend');

    $res->assertSessionHas('status', fn ($m) => str_contains($m, 'sessions and API tokens are ended'));
    expect($target->fresh()->status)->toBe(User::STATUS_SUSPENDED)->and(sessionCount($target))->toBe(0)->and($target->apiTokens()->count())->toBe(0);
    expect(revokedEvents())->toBe(2);
});

test('a failed suspend through the old page leaves sessions, tokens and status alone', function () {
    $admin = User::factory()->create();
    $admin->roles()->create(['role' => Role::ADMIN, 'app_slug' => null]);
    $target = User::factory()->create();
    otherSession($target);
    ApiToken::generate($target->id, 'x');
    User::updating(function ($u) use ($target) {
        if ($u->is($target) && $u->status === User::STATUS_SUSPENDED) {
            throw new RuntimeException('forced failure');
        }
    });

    $this->withoutExceptionHandling();
    try {
        $this->actingAs($admin)->withSession(['2fa_verified' => true])->post('/admin/users/'.$target->id.'/suspend');
    } catch (RuntimeException) {
    }

    expect($target->fresh()->status)->toBe(User::STATUS_ACTIVE)->and(sessionCount($target))->toBe(1)->and($target->apiTokens()->count())->toBe(1);
    expect(revokedEvents())->toBe(0);
});

test('ending sessions can keep one: the session of the request that asked', function () {
    $user = User::factory()->create();
    $keep = otherSession($user);
    otherSession($user);
    otherSession($user);

    $deleted = AccountSessions::end($user, $keep);

    expect($deleted)->toBe(2)->and(DB::table('sessions')->where('user_id', $user->id)->pluck('id')->all())->toBe([$keep]);
    expect(revokedEvents())->toBe(2);
});
