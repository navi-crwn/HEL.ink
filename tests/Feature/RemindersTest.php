<?php

use App\Models\User;
use App\Support\HubDashboard;
use App\Support\Reminders;
use Hop\Ui\Locales;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HOP ID's reminder cards (account checklist, two-factor, recovery codes): a person can hide
 * one, it stays away for seven days, comes back while its task is open, and is gone for good
 * once the task is done.
 */
function withTwoFactor(User $user, int $codes): User
{
    $user->forceFill([
        'two_factor_secret' => encrypt('SECRET'),
        'two_factor_recovery_codes' => encrypt(json_encode(array_map(fn ($i) => 'CODE'.$i, range(1, max($codes, 1))))),
        'two_factor_confirmed_at' => now(),
    ])->save();
    if ($codes === 0) {
        $user->forceFill(['two_factor_recovery_codes' => encrypt(json_encode([]))])->save();
    }

    return $user;
}

/** Some days later, signed in again (the hub signs an idle session out, which is not what these tests are about). */
function daysLater(int $days, User $user): void
{
    test()->travel($days)->days();
    test()->actingAs($user)->withSession(['last_activity_time' => now()]);
}

function dueFor(User $user): array
{
    $dashboard = new HubDashboard($user);

    return Reminders::due($user, $dashboard, $dashboard->health());
}

test('hiding a reminder needs a signed-in person and a known reminder', function () {
    $this->postJson('/reminders/dismiss', ['key' => 'account-health'])->assertUnauthorized();

    $user = User::factory()->create();
    $this->actingAs($user);
    $this->postJson('/reminders/dismiss', ['key' => 'made-up'])->assertStatus(422);
    $this->postJson('/reminders/dismiss', [])->assertStatus(422);
    $this->postJson('/reminders/dismiss', ['key' => ['account-health']])->assertStatus(422);

    expect(DB::table('hub_reminder_dismissals')->count())->toBe(0);
});

test('hiding stores when, once per account and reminder', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($user);

    $this->travelTo(now()->startOfSecond());
    $this->postJson('/reminders/dismiss', ['key' => 'account-health'])
        ->assertOk()->assertJson(['ok' => true, 'key' => 'account-health', 'shows_again_at' => now()->addDays(7)->toIso8601String()]);
    daysLater(2, $user);
    $this->postJson('/reminders/dismiss', ['key' => 'account-health'])->assertOk();

    $rows = DB::table('hub_reminder_dismissals')->get();
    expect($rows)->toHaveCount(1)
        ->and((int) $rows[0]->user_id)->toBe($user->id)
        ->and(Reminders::hidden($user))->toBe(['account-health'])
        ->and(Reminders::hidden($other))->toBe([]);
});

test('the checklist is hidden for seven days, then comes back while a step is open', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $card = 'data-hop-remind="account-health"';

    $this->get('/dashboard')->assertOk()->assertSee($card, false)->assertSee('Account health')
        ->assertSee('data-hop-remind-url="'.route('reminders.dismiss').'"', false);

    $this->postJson('/reminders/dismiss', ['key' => 'account-health'])->assertOk();
    $this->get('/dashboard')->assertOk()->assertDontSee($card, false)->assertDontSee('Account health');

    daysLater(6, $user);
    $this->get('/dashboard')->assertOk()->assertDontSee($card, false);

    daysLater(2, $user); // day 8
    $this->get('/dashboard')->assertOk()->assertSee($card, false)->assertSee('Account health');
});

test('a reminder whose task is done is never drawn, hidden or not', function () {
    $user = User::factory()->create(['avatar' => 'https://example.com/me.png']);
    $user->forceFill(['username' => 'alldone'])->save();
    withTwoFactor($user, 8);

    expect(dueFor($user))->toBe([]);
    $this->actingAs($user)->withSession(['2fa_verified' => true])->get('/dashboard')
        ->assertOk()->assertDontSee('data-hop-remind=', false);
});

test('the two-factor invitation shows only while the checklist is off screen', function () {
    $user = User::factory()->create();

    // The checklist is on screen and already asks for two-factor.
    expect(dueFor($user))->toBe([Reminders::ACCOUNT_HEALTH]);

    Reminders::dismiss($user, Reminders::ACCOUNT_HEALTH);
    expect(dueFor($user))->toBe([Reminders::TWO_FACTOR]);
    $this->actingAs($user)->get('/dashboard')->assertOk()
        ->assertSee('data-hop-remind="two-factor"', false)->assertSee('Two-factor is off');

    Reminders::dismiss($user, Reminders::TWO_FACTOR);
    expect(dueFor($user))->toBe([]);

    $this->travel(8)->days();
    expect(dueFor($user))->toBe([Reminders::ACCOUNT_HEALTH]);
});

test('the recovery codes warning shows when two or fewer are left', function () {
    $many = withTwoFactor(User::factory()->create(), 8);
    $few = withTwoFactor(User::factory()->create(), 2);
    $none = withTwoFactor(User::factory()->create(), 0);

    expect(dueFor($many))->not->toContain(Reminders::RECOVERY_CODES)
        ->and(dueFor($few))->toContain(Reminders::RECOVERY_CODES)
        ->and(dueFor($none))->toContain(Reminders::RECOVERY_CODES);

    $this->actingAs($few)->withSession(['2fa_verified' => true])->get('/dashboard')->assertOk()
        ->assertSee('data-hop-remind="recovery-codes"', false)->assertSee('You have 2 recovery codes left');

    Reminders::dismiss($few, Reminders::RECOVERY_CODES);
    expect(dueFor($few))->not->toContain(Reminders::RECOVERY_CODES);
    $this->travel(8)->days();
    expect(dueFor($few))->toContain(Reminders::RECOVERY_CODES);
});

test('the verify-email banner is functional and cannot be hidden', function () {
    $user = User::factory()->unverified()->create();
    $html = $this->actingAs($user)->get('/settings')->assertOk()->getContent();

    expect($html)->toContain($user->email)->not->toContain('data-hop-remind=');
});

test('the owner account has no checklist and so no reminders about it', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->get('/dashboard')->assertOk()
        ->assertDontSee('data-hop-remind="account-health"', false)->assertDontSee('data-hop-remind="two-factor"', false);
});

test('the reminder strings exist in the nine catalogs', function () {
    $keys = [
        'Shows again next week', 'Hide', 'Two-factor is off', 'Each code works once. Make new ones before you need them.',
        '{0} You have no recovery codes left|{1} You have :count recovery code left|[2,*] You have :count recovery codes left',
    ];
    foreach (Locales::codes() as $lang) {
        $data = json_decode(file_get_contents(base_path("lang/{$lang}.json")), true);
        foreach ($keys as $key) {
            expect(array_key_exists($key, $data))->toBeTrue("{$lang}.json lacks \"{$key}\"");
        }
        app()->setLocale($lang);
        expect(trans_choice($keys[4], 0))->not->toContain('|')->and(trans_choice($keys[4], 2, ['count' => 2]))->toContain('2');
    }
});

test('the migration is idempotent and the table goes with the account', function () {
    $migration = require base_path('database/migrations/2026_11_09_000004_create_hub_reminder_dismissals_table.php');
    $migration->up();
    $migration->up();
    expect(Schema::hasColumns('hub_reminder_dismissals', ['user_id', 'key', 'dismissed_at']))->toBeTrue();

    $user = User::factory()->create();
    Reminders::dismiss($user, Reminders::TWO_FACTOR);
    $this->actingAs($user);
    $json = json_decode($this->post('/settings/export-account')->assertOk()->streamedContent(), true);
    expect($json['hidden_reminders'][0]['key'])->toBe('two-factor');

    $user->delete();
    expect(DB::table('hub_reminder_dismissals')->count())->toBe(0);
});
