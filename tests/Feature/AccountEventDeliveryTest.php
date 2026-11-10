<?php

use App\Jobs\SendHopEvent;
use App\Models\HopApp;
use App\Models\User;
use App\Support\EventRegistry;
use Database\Seeders\HopAppsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/*
 * OP-04: the apps that must hear about account events have an `apps` row with the `events`
 * capability; the seeder adds a missing one and never touches an existing one, and a gap shows up
 * as a warning in `hop:watch` and on the admin accounts page.
 */

// ------------------------------------------------------------------ OP-04

const EVENT_PIXEL_SECRET = 'pixel-secret-that-is-long-enough-1234567890';
const EVENT_NOTE_SECRET = 'notehop-secret-that-is-long-enough-12345';
const EVENT_FILE_SECRET = 'filehop-secret-that-is-long-enough-12345';
const EVENT_PAY_SECRET = 'payhop-secret-that-is-long-enough-12345';
const EVENT_LINK_SECRET = 'linkhop-secret-that-is-long-enough-1234567';
const EVENT_BIO_SECRET = 'biohop-secret-that-is-long-enough-123456789';

function eventEnv(): void
{
    config()->set('hop.sso_secret', null);
    config()->set('hop.apps.pixelhop.url', 'https://pixel.example.com');
    config()->set('hop.apps.pixelhop.secret', EVENT_PIXEL_SECRET);
    config()->set('hop.apps.notehop.url', 'https://note.example.com');
    config()->set('hop.event_apps.notehop.secret', EVENT_NOTE_SECRET);
    config()->set('hop.apps.filehop.url', 'https://file.example.com');
    config()->set('hop.event_apps.filehop.secret', EVENT_FILE_SECRET);
    config()->set('hop.apps.payhop.url', 'https://pay.example.com');
    config()->set('hop.event_apps.payhop.secret', EVENT_PAY_SECRET);
    config()->set('hop.apps.linkhop.url', 'https://link.example.com');
    config()->set('hop.apps.linkhop.secret', EVENT_LINK_SECRET);
    config()->set('hop.apps.biohop.url', 'https://bio.example.com');
    config()->set('hop.apps.biohop.secret', EVENT_BIO_SECRET);
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

test('the seeder adds filehop like notehop: its own secret, the sso callback, the events capability', function () {
    eventEnv();

    $this->seed(HopAppsSeeder::class);

    $file = HopApp::where('slug', 'filehop')->first();
    expect($file)->not->toBeNull()
        ->and($file->can('events'))->toBeTrue()
        ->and($file->secret)->toBe(EVENT_FILE_SECRET)
        ->and($file->url)->toBe('https://file.example.com')
        ->and($file->callback_path)->toBe('/auth/sso/callback')
        ->and($file->start_path)->toBe('/auth/sso')
        ->and($file->menu_label)->toBe('Files')
        ->and($file->can('admin_api'))->toBeTrue()
        ->and($file->capabilities)->toEqualCanonicalizing(['admin_api', 'events', 'zero_knowledge']);
});

test('the seeder adds payhop like filehop: its own secret, the sso callback, the events capability', function () {
    eventEnv();

    $this->seed(HopAppsSeeder::class);

    $pay = HopApp::where('slug', 'payhop')->first();
    expect($pay)->not->toBeNull()
        ->and($pay->can('events'))->toBeTrue()
        ->and($pay->secret)->toBe(EVENT_PAY_SECRET)
        ->and($pay->url)->toBe('https://pay.example.com')
        ->and($pay->callback_path)->toBe('/auth/sso/callback')
        ->and($pay->start_path)->toBe('/auth/sso')
        ->and($pay->menu_label)->toBe('Payments')
        ->and($pay->can('admin_api'))->toBeTrue()
        ->and($pay->capabilities)->toEqualCanonicalizing(['admin_api', 'events', 'zero_knowledge']);
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
    expect(EventRegistry::warnings())->toHaveCount(count(config('hop.event_apps')))
        ->and(EventRegistry::warnings()[0])->toContain('pixelhop')->toContain('no row');

    HopApp::factory()->pixelhop(EVENT_PIXEL_SECRET)->create(['capabilities' => ['account_api']]);
    HopApp::factory()->notehop(EVENT_NOTE_SECRET)->create(['capabilities' => ['events']]);
    HopApp::factory()->filehop(EVENT_FILE_SECRET)->create();
    HopApp::factory()->payhop(EVENT_PAY_SECRET)->create();
    HopApp::factory()->create(['slug' => 'linkhop', 'url' => 'https://link.example.com', 'secret' => EVENT_LINK_SECRET, 'capabilities' => ['events']]);
    HopApp::factory()->create(['slug' => 'biohop', 'url' => 'https://bio.example.com', 'secret' => EVENT_BIO_SECRET, 'capabilities' => ['events']]);
    $warnings = EventRegistry::warnings();

    expect($warnings)->toHaveCount(1)
        ->and($warnings[0])->toContain('pixelhop')->toContain('events');
});

test('a disabled event app is not expected to have a row', function () {
    eventEnv();
    foreach (array_keys(config('hop.event_apps')) as $slug) {
        config()->set("hop.apps.{$slug}.enabled", false);
    }

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

// ------------------------------------------------------------------ LinkHop and BioHop receive events too

test('the seeder also adds linkhop and biohop, each with the secret the hub already uses to sign people in to it', function () {
    eventEnv();

    $this->seed(HopAppsSeeder::class);

    $link = HopApp::where('slug', 'linkhop')->first();
    $bio = HopApp::where('slug', 'biohop')->first();
    expect($link)->not->toBeNull()->and($bio)->not->toBeNull()
        ->and($link->can('events'))->toBeTrue()->and($bio->can('events'))->toBeTrue()
        ->and($link->secret)->toBe(EVENT_LINK_SECRET)->and($bio->secret)->toBe(EVENT_BIO_SECRET)
        ->and($link->url)->toBe('https://link.example.com')->and($bio->url)->toBe('https://bio.example.com')
        ->and($link->callback_path)->toBe('/auth/sso/callback')->and($bio->callback_path)->toBe('/auth/sso/callback')
        ->and($link->isConfigured())->toBeTrue()
        ->and(EventRegistry::warnings())->toBe([]);
});

test('linkhop and biohop fall back to the shared sso secret, as the hub signs their tickets with it', function () {
    eventEnv();
    config()->set('hop.apps.linkhop.secret', null);
    config()->set('hop.apps.biohop.secret', null);
    config()->set('hop.sso_secret', 'shared-sso-secret-that-is-long-enough-123456');

    $this->seed(HopAppsSeeder::class);

    expect(HopApp::where('slug', 'linkhop')->first()->secret)->toBe('shared-sso-secret-that-is-long-enough-123456')
        ->and(HopApp::where('slug', 'biohop')->first()->secret)->toBe('shared-sso-secret-that-is-long-enough-123456');
});

test('an existing linkhop row, made long ago, is left exactly as it is, and only a missing events capability is a warning', function () {
    eventEnv();
    $old = HopApp::factory()->create(['slug' => 'linkhop', 'url' => 'https://link.hel.example', 'secret' => 'the-old-linkhop-secret-that-is-long-enough-1', 'capabilities' => ['account_api']]);
    $before = DB::table('apps')->where('id', $old->id)->first();

    $this->seed(HopAppsSeeder::class);

    expect(DB::table('apps')->where('id', $old->id)->first())->toEqual($before)
        ->and(HopApp::where('slug', 'linkhop')->count())->toBe(1)
        ->and(collect(EventRegistry::warnings())->filter(fn ($w) => str_starts_with($w, 'linkhop:'))->all())->toHaveCount(1)
        ->and(collect(EventRegistry::warnings())->first(fn ($w) => str_starts_with($w, 'linkhop:')))->toContain('events');
});

test('without linkhop and biohop rows the warning names them and says what to run', function () {
    eventEnv();

    $text = implode("\n", EventRegistry::warnings());

    expect($text)->toContain('linkhop: no row')->toContain('biohop: no row')->toContain('HopAppsSeeder');
});

test('after seeding, one sign-out at the hub queues session.revoked for every event app', function () {
    eventEnv();
    $this->seed(HopAppsSeeder::class);
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect();

    Queue::assertPushed(SendHopEvent::class, count(config('hop.event_apps')));
    foreach (array_keys(config('hop.event_apps')) as $slug) {
        $id = HopApp::where('slug', $slug)->value('id');
        Queue::assertPushed(SendHopEvent::class, fn ($j) => $j->appId === $id && $j->type === 'session.revoked');
    }
});

test('the queued event reaches linkhop at /hop/events, signed with its secret as the hub (helink)', function () {
    eventEnv();
    $this->seed(HopAppsSeeder::class);
    Http::fake(['https://link.example.com/hop/events' => Http::response(['ok' => true], 200)]);
    $row = HopApp::where('slug', 'linkhop')->first();

    (new SendHopEvent($row->id, 'session.revoked', ['sub' => '7', 'hop_id' => '7'], 'evt-1', now()->toIso8601String()))->handle();

    Http::assertSent(function ($request) {
        $base = implode('|', ['hop1', 'helink', $request->header('X-HOP-Timestamp')[0], $request->header('X-HOP-Nonce')[0], 'POST', '/hop/events', hash('sha256', $request->body())]);
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', $base, EVENT_LINK_SECRET, true)), '+/', '-_'), '=');

        return $request->url() === 'https://link.example.com/hop/events' && $request->header('X-HOP-App')[0] === 'helink' && $request->header('X-HOP-Signature')[0] === $sig
            && json_decode($request->body(), true)['type'] === 'session.revoked';
    });
});
