<?php

use App\Models\ActivityLog;
use App\Models\LoginHistory;
use App\Models\ServiceCheck;
use App\Models\User;
use App\Support\HopApps;
use App\Support\HubDashboard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

function signIn(array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    test()->actingAs($user);

    return $user;
}

/** A user with everything on the checklist done. */
function fullySetUp(): User
{
    $user = User::factory()->create(['avatar' => 'https://example.com/me.png']);
    $user->forceFill([
        'username' => 'fullyset',
        'two_factor_secret' => encrypt('SECRET'),
        'two_factor_recovery_codes' => encrypt(json_encode(['AAAAAAAAAA', 'BBBBBBBBBB'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $user;
}

function signInRow(User $user, array $attributes = []): LoginHistory
{
    return LoginHistory::create($attributes + ['user_id' => $user->id, 'logged_in_at' => now(), 'device' => 'Desktop', 'browser' => 'Firefox']);
}

function activityRow(User $user, string $description, ?Carbon $at = null, string $action = 'updated', string $model = 'Profile'): ActivityLog
{
    $log = (new ActivityLog)->forceFill([
        'user_id' => $user->id, 'action' => $action, 'model' => $model, 'model_id' => $user->id, 'description' => $description,
        'created_at' => $at ?? now(), 'updated_at' => $at ?? now(),
    ]);
    $log->save();

    return $log;
}

test('a guest is sent to sign in', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('the dashboard renders for a fresh user', function () {
    $user = signIn(['name' => 'Ayu Lestari', 'timezone' => 'UTC']);

    $this->get('/dashboard')
        ->assertOk()
        ->assertSee('Ayu', false)
        ->assertSee('My apps')
        ->assertSee('Account health')
        ->assertSee('2 of 6 done')
        ->assertSee('Choose a username')
        ->assertSee('Security')
        ->assertSee('No sign-ins recorded yet.')
        ->assertSee('Recent activity')
        ->assertSee('No activity yet.')
        ->assertSee('Data and privacy')
        ->assertSee(route('settings').'#backup', false)
        ->assertSee(route('settings').'#account', false)
        ->assertDontSee('flagcdn.com');
    expect($user->hasTwoFactor())->toBeFalse();
});

test('the dashboard renders for a fully set-up user', function () {
    $user = fullySetUp();
    signInRow($user, ['city' => 'Jakarta', 'country' => 'ID', 'country_name' => 'Indonesia']);
    activityRow($user, 'Changed profile photo');

    $this->actingAs($user)->withSession(['2fa_verified' => true])->get('/dashboard')
        ->assertOk()
        // Every step done: the checklist is a reminder and is gone for good.
        ->assertDontSee('Account health')
        ->assertDontSee('data-hop-remind="account-health"', false)
        ->assertSee('Two-factor is on')
        ->assertSee('Jakarta, Indonesia')
        ->assertSee('Changed profile photo')
        ->assertSee('Firefox');
    $health = (new HubDashboard($user))->health();
    expect($health['done'])->toBe(6)->and($health['percent'])->toBe(100);
});

test('a Google-only user without a password still gets a working dashboard', function () {
    $user = signIn(['google_id' => 'g-123', 'name' => 'Gina Google']);
    // Google sign-up stores a random placeholder password the person never knew.
    DB::table('users')->where('id', $user->id)->update(['password' => bcrypt('TEMPORARY_GOOGLE_OAUTH_NO_PASSWORD_x')]);

    $this->get('/dashboard')
        ->assertOk()
        ->assertSee('Set a password')
        ->assertSee('Set a password first');
    $health = collect((new HubDashboard($user->fresh()))->health()['steps'])->keyBy('key');
    expect($health['password']['done'])->toBeFalse()
        ->and($health['two-factor']['done'])->toBeFalse()
        ->and($health['two-factor']['url'])->toEndWith('#account');

    // An empty password column counts as no password, too.
    DB::table('users')->where('id', $user->id)->update(['password' => '']);
    $this->get('/dashboard')->assertOk()->assertSee('Set a password');
});

test('the health steps are computed from the account', function () {
    $user = User::factory()->unverified()->create();
    $steps = fn (User $u) => collect((new HubDashboard($u->fresh()))->health()['steps'])->mapWithKeys(fn ($s) => [$s['key'] => $s['done']])->all();

    expect($steps($user))->toBe(['email' => false, 'password' => true, 'two-factor' => false, 'recovery' => false, 'username' => false, 'photo' => false]);

    $user->forceFill(['email_verified_at' => now(), 'username' => 'ayu'])->save();
    expect($steps($user))->toMatchArray(['email' => true, 'username' => true, 'photo' => false]);

    $user->forceFill(['avatar_path' => 'avatars/x.webp'])->save();
    expect($steps($user)['photo'])->toBeTrue();

    // Two-factor started but not confirmed does not count; recovery codes follow two-factor.
    $user->forceFill(['two_factor_secret' => encrypt('S'), 'two_factor_recovery_codes' => encrypt(json_encode(['A']))])->save();
    expect($steps($user))->toMatchArray(['two-factor' => false, 'recovery' => false]);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();
    expect($steps($user))->toMatchArray(['two-factor' => true, 'recovery' => true]);

    // All codes used up: ask for new ones.
    $user->forceFill(['two_factor_recovery_codes' => encrypt(json_encode([]))])->save();
    expect($steps($user))->toMatchArray(['two-factor' => true, 'recovery' => false]);
});

test('there is no export step, because an export leaves no trace', function () {
    $keys = collect((new HubDashboard(User::factory()->create()))->health()['steps'])->pluck('key');
    expect($keys->all())->not->toContain('export')->and($keys)->toHaveCount(6);
});

test('a Google account counts as having a password once one was set through HOP', function () {
    $user = User::factory()->create(['google_id' => 'g-1', 'password' => 'placeholder-123']);
    expect((new HubDashboard($user))->hasOwnPassword())->toBeFalse();

    $this->actingAs($user)->put('/password', ['current_password' => 'placeholder-123', 'password' => 'a-new-Password-77!', 'password_confirmation' => 'a-new-Password-77!'])
        ->assertSessionHasNoErrors();
    expect(ActivityLog::where('user_id', $user->id)->where('model', 'Password')->exists())->toBeTrue()
        ->and((new HubDashboard($user->fresh()))->hasOwnPassword())->toBeTrue();
});

test('a password reset by email leaves an activity row', function () {
    $user = User::factory()->create(['google_id' => 'g-2']);
    $token = Password::broker()->createToken($user);

    $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'Reset-Pass-99!x', 'password_confirmation' => 'Reset-Pass-99!x']);
    expect(ActivityLog::where('user_id', $user->id)->where('model', 'Password')->count())->toBe(1)
        ->and((new HubDashboard($user->fresh()))->hasOwnPassword())->toBeTrue();
});

test('the security card lists only my last three sign-ins, newest first', function () {
    $me = signIn();
    $other = User::factory()->create();
    foreach ([['Oldtown', 5], ['Midtown', 4], ['Uptown', 3], ['Newtown', 1]] as [$city, $hoursAgo]) {
        signInRow($me, ['city' => $city, 'country' => 'ID', 'country_name' => 'Indonesia', 'logged_in_at' => now()->subHours($hoursAgo)]);
    }
    signInRow($other, ['city' => 'Atlantis', 'country' => 'ZZ', 'country_name' => 'Nowhere', 'device' => 'OtherPhone', 'browser' => 'OtherBrowser', 'ip_address' => '203.0.113.9']);

    $this->get('/dashboard')
        ->assertOk()
        ->assertSeeInOrder(['Newtown, Indonesia', 'Uptown, Indonesia', 'Midtown, Indonesia'])
        ->assertDontSee('Oldtown')
        ->assertDontSee('Atlantis')
        ->assertDontSee('OtherPhone')
        ->assertDontSee('OtherBrowser')
        ->assertDontSee('203.0.113.9')
        ->assertSee(route('settings').'#login', false);
});

test('the security card falls back when no place was stored and flags a proxy', function () {
    $me = signIn();
    signInRow($me, ['device' => null, 'browser' => null, 'is_proxy' => true]);

    $this->get('/dashboard')->assertOk()->assertSee('Unknown location')->assertSee('Unknown device')->assertSee('Proxy');
});

test('the activity card shows my five latest rows and nobody else\'s', function () {
    $me = signIn();
    $other = User::factory()->create();
    foreach (range(1, 7) as $i) {
        activityRow($me, "My action number {$i}x", now()->subMinutes(10 - $i), $i === 7 ? 'created' : 'updated');
    }
    activityRow($other, 'Secret action of someone else');

    $response = $this->get('/dashboard')->assertOk();
    $response->assertSeeInOrder(['My action number 7x', 'My action number 6x', 'My action number 5x', 'My action number 4x', 'My action number 3x'])
        ->assertDontSee('My action number 2x')
        ->assertDontSee('My action number 1x')
        ->assertDontSee('Secret action of someone else')
        ->assertSee('Created')
        ->assertSee(route('settings').'#activity', false);
});

test('the hour decides the greeting in the person\'s timezone', function () {
    $user = User::factory()->make(['timezone' => 'Asia/Jakarta']);
    $at = fn (string $utc) => (new HubDashboard($user, Carbon::parse($utc, 'UTC')))->period();

    expect($at('2026-11-02 01:00:00'))->toBe('morning')   // 08:00 in Jakarta
        ->and($at('2026-11-02 06:00:00'))->toBe('afternoon') // 13:00
        ->and($at('2026-11-02 12:00:00'))->toBe('evening');  // 19:00

    $bogus = User::factory()->make(['timezone' => 'Nowhere/City']);
    expect((new HubDashboard($bogus, Carbon::parse('2026-11-02 09:00:00', 'UTC')))->period())->toBeIn(['morning', 'afternoon', 'evening']);
});

test('the greeting is rendered by the server when a timezone is saved', function () {
    signIn(['name' => 'Budi Santoso', 'timezone' => 'UTC']);
    $this->travelTo(Carbon::parse('2026-11-02 20:30:00', 'UTC'));

    $this->get('/dashboard')->assertSee('Good evening, ', false)->assertSee('<span class="hop-serif">Budi</span>', false);
});

test('the status line follows the watchdog', function () {
    $apps = HopApps::all();
    $summary = fn (array $oks) => collect($apps)->values()->mapWithKeys(fn ($a, $i) => [$a->slug => ['app' => $a, 'ok' => $oks[$i] ?? null]])->all();

    expect(HubDashboard::status($summary([]))['state'])->toBe('unknown');
    expect(HubDashboard::status($summary(array_fill(0, count($apps), true))))->toMatchArray(['state' => 'ok', 'up' => count($apps), 'down' => []]);
    $down = HubDashboard::status($summary([true, false] + array_fill(0, count($apps), true)));
    expect($down['state'])->toBe('problem')->and($down['down'])->toBe([$apps[1]->name]);

    signIn();
    $this->get('/dashboard')->assertSee('Status checks have not run yet');
    foreach ($apps as $app) {
        ServiceCheck::create(['app_slug' => $app->slug, 'kind' => 'health', 'ok' => true, 'checked_at' => now()]);
    }
    $this->get('/dashboard')->assertSee(count($apps).' apps running, all normal');
    ServiceCheck::create(['app_slug' => $apps[0]->slug, 'kind' => 'health', 'ok' => false, 'error' => 'HTTP 503', 'checked_at' => now()->addMinute()]);
    $this->get('/dashboard')->assertSee('Problem with '.$apps[0]->name);
});

test('app cards show status, open through SSO and leave the numbers slot empty', function () {
    $app = HopApps::all()[0];
    $render = fn (?array $state, string $extra = '') => Blade::render('<x-app-card :app="$app" :state="$state">'.$extra.'</x-app-card>', ['app' => $app, 'state' => $state]);

    expect($render(['ok' => true, 'checked_at' => now(), 'error' => null]))
        ->toContain($app->name)->toContain('Up')->toContain('Visit site')->not->toContain('glass-inset');
    expect($render(['ok' => false, 'checked_at' => now(), 'error' => 'HTTP 503']))->toContain('Down')->toContain('HTTP 503');
    expect($render(null))->toContain('Unknown')->toContain('No check yet');
    expect($render(null, '<x-slot name="stats">12 links</x-slot>'))->toContain('12 links')->toContain('glass-inset');

    signIn();
    $page = $this->get('/dashboard')->assertOk();
    foreach (HopApps::all() as $a) {
        if ($a->isConfigured()) {
            $page->assertSee(route('apps.open', ['app' => $a->slug]), false);
        }
    }
    // The card can also be rendered without the Open row.
    expect(Blade::render('<x-app-card :app="$app" :open="false" />', ['app' => $app]))->not->toContain('Visit site');
});

test('the dashboard asks the database for a bounded number of queries however much history there is', function () {
    $user = signIn();
    $count = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        test()->get('/dashboard')->assertOk();
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    };
    $count(); // warm-up: the first request pays one-off queries
    foreach (range(1, 3) as $i) {
        signInRow($user, ['logged_in_at' => now()->subMinutes($i)]);
        activityRow($user, "Row {$i}", now()->subMinutes($i));
    }
    $few = $count();
    foreach (range(1, 80) as $i) {
        signInRow($user, ['logged_in_at' => now()->subDays($i)]);
        activityRow($user, "Row {$i}", now()->subDays($i));
    }
    $many = $count();

    expect($many)->toBe($few)->and($many)->toBeLessThan(45);
});

test('the owner account sees no steps it cannot take', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->get('/dashboard')->assertOk()->assertDontSee('Account health')->assertDontSee('Delete account');
});

test('the dashboard strings are translated in every shipped language', function () {
    $keys = ['My apps', 'Account health', "This wasn't me", 'Good morning, :name', 'Recent activity', 'Data and privacy', 'Set a password first'];
    foreach (glob(lang_path('*.json')) as $file) {
        $strings = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        foreach ($keys as $key) {
            expect($strings)->toHaveKey($key);
        }
    }
    app()->setLocale('id');
    expect(trans_choice('{1} :count app running, all normal|[2,*] :count apps running, all normal', 4, ['count' => 4]))->toBe('4 app berjalan, semuanya normal');
});
