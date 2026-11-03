<?php

use App\Models\HopApp;
use App\Models\User;
use App\Support\EventRegistry;
use Database\Seeders\HopAppsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/*
 * OP-04: the apps that must hear about account events have an `apps` row with the `events`
 * capability; the seeder adds a missing one and never touches an existing one, and a gap shows up
 * as a warning in `hop:watch` and on the admin accounts page.
 */

// ------------------------------------------------------------------ OP-04

const EVENT_PIXEL_SECRET = 'pixel-secret-that-is-long-enough-1234567890';
const EVENT_NOTE_SECRET = 'notehop-secret-that-is-long-enough-12345';

function eventEnv(): void
{
    config()->set('hop.sso_secret', null);
    config()->set('hop.apps.pixelhop.url', 'https://pixel.example.com');
    config()->set('hop.apps.pixelhop.secret', EVENT_PIXEL_SECRET);
    config()->set('hop.apps.notehop.url', 'https://note.example.com');
    config()->set('hop.event_apps.notehop.secret', EVENT_NOTE_SECRET);
}

test('the seeder adds the pixelhop and notehop rows with the events capability and the secret from the environment', function () {
    eventEnv();

    $this->seed(HopAppsSeeder::class);

    $pixel = HopApp::where('slug', 'pixelhop')->first();
    $note = HopApp::where('slug', 'notehop')->first();
    expect($pixel->can('events'))->toBeTrue()
        ->and($pixel->secret)->toBe(EVENT_PIXEL_SECRET)
        ->and($pixel->url)->toBe('https://pixel.example.com')
        ->and($pixel->callback_path)->toBe('/auth/helink/callback')
        ->and($note->can('events'))->toBeTrue()
        ->and($note->secret)->toBe(EVENT_NOTE_SECRET)
        ->and($note->callback_path)->toBe('/auth/sso/callback')
        ->and(EventRegistry::warnings())->toBe([]);
});

test('running the seeder again, or on a database that has the rows, changes nothing', function () {
    eventEnv();
    $existing = HopApp::factory()->notehop('already-there-secret-that-is-long-enough-123')->create(['capabilities' => ['account_api'], 'url' => 'https://note.hel.example']);
    $before = DB::table('apps')->where('id', $existing->id)->first();

    $this->seed(HopAppsSeeder::class);
    $this->seed(HopAppsSeeder::class);

    expect(HopApp::where('slug', 'notehop')->count())->toBe(1)
        ->and(HopApp::where('slug', 'pixelhop')->count())->toBe(1)
        ->and(DB::table('apps')->where('id', $existing->id)->first())->toEqual($before)
        ->and(HopApp::find($existing->id)->secret)->toBe('already-there-secret-that-is-long-enough-123');
});

test('the seeder does not invent a secret: without one in the environment no row is made', function () {
    config()->set('hop.sso_secret', null);
    config()->set('hop.apps.pixelhop.secret', null);
    config()->set('hop.apps.notehop.url', 'https://note.example.com');
    config()->set('hop.event_apps.notehop.secret', null);

    expect(EventRegistry::ensure())->each->toStartWith('skipped');
    expect(HopApp::count())->toBe(0);
});

test('a configured event app with no row, or a row without events, is a warning; a good row is not', function () {
    eventEnv();
    expect(EventRegistry::warnings())->toHaveCount(2)
        ->and(EventRegistry::warnings()[0])->toContain('pixelhop')->toContain('no row');

    HopApp::factory()->pixelhop(EVENT_PIXEL_SECRET)->create(['capabilities' => ['account_api']]);
    HopApp::factory()->notehop(EVENT_NOTE_SECRET)->create(['capabilities' => ['events']]);
    $warnings = EventRegistry::warnings();

    expect($warnings)->toHaveCount(1)
        ->and($warnings[0])->toContain('pixelhop')->toContain('events');
});

test('a disabled event app is not expected to have a row', function () {
    eventEnv();
    config()->set('hop.apps.pixelhop.enabled', false);
    config()->set('hop.apps.notehop.enabled', false);

    expect(EventRegistry::warnings())->toBe([]);
});

test('hop:watch prints the warning and the admin accounts page shows it', function () {
    eventEnv();
    Http::fake();

    $this->artisan('hop:watch')->expectsOutputToContain('no row in the apps table');

    $admin = User::factory()->admin()->create();
    $this->withoutVite()->actingAs($admin)->withSession(['2fa_verified' => true])->get('/admin/users')
        ->assertOk()->assertSee('Some apps will not hear about account changes')->assertSee('pixelhop: no row');
});

test('the admin accounts page shows no warning when the event apps have their rows', function () {
    eventEnv();
    $this->seed(HopAppsSeeder::class);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->withSession(['2fa_verified' => true])->get('/admin/users')
        ->assertOk()->assertDontSee('Some apps will not hear about account changes');
});
