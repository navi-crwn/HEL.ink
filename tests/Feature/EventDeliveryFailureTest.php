<?php

use App\Jobs\SendHopEvent;
use App\Models\HopApp;
use App\Support\EventRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/*
 * A session.revoked (or any account event) that never reaches an app, after every retry, shows up
 * in the hub's event warnings (the admin users page and hop:watch), without anything about the person.
 */

test('a permanently failed session.revoked is reported for its app, with a count and a time and no person', function () {
    HopApp::factory()->payhop()->create(['capabilities' => ['events']]);
    Http::fake(['https://pay.example.com/hop/events' => Http::response('bad signature', 401)]);
    $job = new SendHopEvent(HopApp::where('slug', 'payhop')->value('id'), 'session.revoked', ['sub' => '4242', 'hop_id' => '4242'], 'ev-1', now()->toIso8601String());

    expect(collect(EventRegistry::warnings())->filter(fn ($l) => str_starts_with($l, 'payhop:')))->toBeEmpty();
    foreach ([1, 2] as $_) {
        try {
            $job->handle();
        } catch (Throwable $e) {
            $job->failed($e);
        }
    }

    $lines = array_values(array_filter(EventRegistry::warnings(), fn ($l) => str_starts_with($l, 'payhop:')));
    expect($lines)->toHaveCount(1)
        ->and($lines[0])->toContain('2 account event(s)')->toContain('session.revoked')->not->toContain('4242');
});

test('a delivery that works again clears the warning', function () {
    HopApp::factory()->payhop()->create(['capabilities' => ['events']]);
    Cache::forever(SendHopEvent::failureKey('payhop'), ['count' => 3, 'last_at' => now()->toIso8601String(), 'last_type' => 'session.revoked']);
    Http::fake(['https://pay.example.com/hop/events' => Http::response(['ok' => true])]);

    (new SendHopEvent(HopApp::where('slug', 'payhop')->value('id'), 'session.revoked', ['sub' => '1'], 'ev-2', now()->toIso8601String()))->handle();

    expect(collect(EventRegistry::warnings())->filter(fn ($l) => str_starts_with($l, 'payhop:')))->toBeEmpty();
});

test('through the real queue, the last failed attempt records the failure', function () {
    config()->set('queue.default', 'database');
    HopApp::factory()->payhop()->create(['capabilities' => ['events']]);
    Http::fake(['https://pay.example.com/hop/events' => Http::response('no', 403)]);
    SendHopEvent::broadcast('session.revoked', ['sub' => '9', 'hop_id' => '9']);

    for ($i = 0; $i < 8; $i++) {
        DB::table('jobs')->update(['available_at' => now()->timestamp]); // skip the backoff wait
        $this->artisan('queue:work', ['--once' => true, '--tries' => 8]);
    }

    expect(DB::table('jobs')->count())->toBe(0)
        ->and(collect(EventRegistry::warnings())->filter(fn ($l) => str_starts_with($l, 'payhop:'))->first())->toContain('1 account event(s)');
});
