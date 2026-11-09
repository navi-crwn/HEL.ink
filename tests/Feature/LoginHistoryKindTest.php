<?php

use App\Models\LoginHistory;
use App\Models\User;
use App\Services\LoginRecorder;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\DB;

/**
 * Opening the apps through the hub (single sign-on hand-offs) used to fill the five-row
 * sign-in list, so the real sign-ins people check for intruders disappeared.
 */
function recorder(): LoginRecorder
{
    return app(LoginRecorder::class);
}

function flushDeferred(): void
{
    app(DeferredCallbackCollection::class)->invoke();
}

test('a password or Google sign-in is a sign-in, an app hand-off is a hand-off', function () {
    $user = User::factory()->create();

    recorder()->record($user, '203.0.113.5', 'Mozilla/5.0', 'password');
    recorder()->record($user, '203.0.113.5', 'Mozilla/5.0', 'google');
    recorder()->record($user, '203.0.113.5', 'Mozilla/5.0', 'FileHop');
    flushDeferred();

    expect(LoginHistory::where('user_id', $user->id)->signIns()->count())->toBe(2)
        ->and(LoginHistory::where('user_id', $user->id)->handoffs()->count())->toBe(1)
        ->and(LoginHistory::where('user_id', $user->id)->handoffs()->value('provider'))->toBe('FileHop');
});

test('five app hand-offs do not push the real sign-ins out of the list', function () {
    $user = User::factory()->create();
    $at = now()->subDays(3);
    LoginHistory::create(['user_id' => $user->id, 'kind' => 'login', 'logged_in_at' => $at, 'city' => 'Jakarta', 'country' => 'ID', 'device' => 'Desktop', 'browser' => 'Firefox']);
    foreach (['LinkHop', 'BioHop', 'PixelHop', 'NoteHop', 'FileHop', 'LinkHop'] as $i => $app) {
        LoginHistory::create(['user_id' => $user->id, 'kind' => 'sso', 'provider' => $app, 'logged_in_at' => now()->subMinutes(10 - $i), 'device' => 'Mobile', 'browser' => 'Mobile Safari']);
    }
    LoginHistory::keepLatest($user->id);

    // The hand-offs are trimmed to five on their own; the sign-in stays.
    expect(LoginHistory::where('user_id', $user->id)->handoffs()->count())->toBe(5)
        ->and(LoginHistory::where('user_id', $user->id)->signIns()->count())->toBe(1);

    $this->actingAs($user);
    $settings = $this->get('/settings')->assertOk();
    $settings->assertSee('Jakarta')->assertSee('App sign-ins')->assertSee('PixelHop');
    // The dashboard's security card lists the sign-in, not the hand-offs.
    $this->get('/dashboard')->assertOk()->assertSee('Jakarta, Indonesia')->assertDontSee('Mobile Safari');
});

test('the migration marks old rows whose provider is an app name as hand-offs', function () {
    $user = User::factory()->create();
    DB::table('login_histories')->insert([
        ['user_id' => $user->id, 'kind' => 'login', 'provider' => 'Telkomsel', 'logged_in_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['user_id' => $user->id, 'kind' => 'login', 'provider' => 'PixelHop', 'logged_in_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['user_id' => $user->id, 'kind' => 'login', 'provider' => null, 'logged_in_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    $migration = require base_path('database/migrations/2026_11_09_000002_login_histories_kind.php');
    $migration->up();
    $migration->up(); // idempotent

    expect(LoginHistory::where('user_id', $user->id)->handoffs()->pluck('provider')->all())->toBe(['PixelHop'])
        ->and(LoginHistory::where('user_id', $user->id)->signIns()->count())->toBe(2);
});

test('the account export tells sign-ins and hand-offs apart', function () {
    $user = User::factory()->create();
    LoginHistory::create(['user_id' => $user->id, 'kind' => 'sso', 'provider' => 'NoteHop', 'logged_in_at' => now()]);
    $this->actingAs($user);

    $json = json_decode($this->post('/settings/export-account')->assertOk()->streamedContent(), true);

    expect($json['login_history'][0]['kind'])->toBe('sso');
});
