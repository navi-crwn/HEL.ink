<?php

use App\Jobs\SendHopEvent;
use App\Models\HopApp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/*
 * OP-03: signing out at the hub reaches the apps through the queue, so an app that is down
 * during the redirect chain still ends the person's sessions once it is back.
 */

const LOGOUT_PIXEL_SECRET = 'pixel-secret-that-is-long-enough-1234567890';
const LOGOUT_NOTE_SECRET = 'notehop-secret-that-is-long-enough-12345';

test('signing out at the hub queues session.revoked for every app that listens, with the id from before the logout', function () {
    Queue::fake();
    HopApp::factory()->pixelhop(LOGOUT_PIXEL_SECRET)->create(['capabilities' => ['events']]);
    HopApp::factory()->notehop(LOGOUT_NOTE_SECRET)->create(['capabilities' => ['events']]);
    HopApp::factory()->create(['slug' => 'quiet', 'capabilities' => ['account_api']]);
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect();

    $this->assertGuest();
    Queue::assertPushed(SendHopEvent::class, 2);
    Queue::assertPushed(SendHopEvent::class, fn ($j) => $j->type === 'session.revoked'
        && $j->data === ['sub' => (string) $user->id, 'hop_id' => (string) $user->id]);
});

test('a GET /logout without a hub session queues nothing', function () {
    Queue::fake();
    HopApp::factory()->pixelhop(LOGOUT_PIXEL_SECRET)->create(['capabilities' => ['events']]);

    $this->get('/logout')->assertRedirect();

    Queue::assertNothingPushed();
});

test('the logout redirect chain is still there as the visible part of signing out', function () {
    Queue::fake();
    config()->set('hop.apps.linkhop.logout', '/auth/sso/logout');
    $res = $this->actingAs(User::factory()->create())->post('/logout');

    expect($res->headers->get('Location'))->toContain('/auth/sso/logout?next=');
});

test('signing out still works when the queue cannot take the event', function () {
    HopApp::factory()->pixelhop(LOGOUT_PIXEL_SECRET)->create(['capabilities' => ['events']]);
    config()->set('queue.default', 'sync');
    Http::fake(fn () => throw new RuntimeException('app unreachable'));

    $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect();

    $this->assertGuest();
});

test('an app that is down at sign-out gets the event when it is back (queue retry)', function () {
    config()->set('queue.default', 'database');
    HopApp::factory()->pixelhop(LOGOUT_PIXEL_SECRET)->create(['capabilities' => ['events']]);
    Http::fake(['https://p.example.com/hop/events' => Http::sequence()->pushFailedConnection('down')->push(['ok' => true])]);
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect();
    expect(DB::table('jobs')->count())->toBe(1);

    // First try: the app is down. The job is kept, not lost, and waits the first backoff step (30 s).
    $this->artisan('queue:work', ['--once' => true, '--tries' => 8])->assertSuccessful();
    expect(DB::table('jobs')->count())->toBe(1)
        ->and(DB::table('jobs')->value('attempts'))->toBe(1)
        ->and((int) DB::table('jobs')->value('available_at'))->toBeGreaterThan(now()->timestamp + 25);
    Http::assertSentCount(1);

    // The app is back: the retry delivers it and the queue is empty.
    $this->travel(31)->seconds();
    $this->artisan('queue:work', ['--once' => true, '--tries' => 8])->assertSuccessful();
    expect(DB::table('jobs')->count())->toBe(0);
    Http::assertSentCount(2);
    Http::assertSent(fn ($req) => json_decode($req->body(), true)['type'] === 'session.revoked'
        && json_decode($req->body(), true)['data']['sub'] === (string) $user->id);
});
