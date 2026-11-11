<?php

use App\Http\Controllers\SsoController;
use App\Jobs\SendHopEvent;
use App\Models\HopApp;
use App\Models\User;
use App\Support\HopApps;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

/*
 * Signing out: the back channel (a queued session.revoked) ends the sessions of every app that listens,
 * and those apps stay out of the browser redirect chain. Only apps without `events` are walked through.
 */

/** The hosts the logout chain sends the browser through, in order, and where it finally ends. */
function logoutWalk(string $location): array
{
    $hosts = [];
    while (($q = parse_url($location, PHP_URL_QUERY)) && str_contains($q, 'next=')) {
        $hosts[] = parse_url($location, PHP_URL_HOST);
        parse_str($q, $out);
        $location = $out['next'];
    }

    return [$hosts, $location];
}

function eventApp(string $slug, bool $events = true): void
{
    $factory = HopApp::factory();
    $factory = match ($slug) {
        'pixelhop' => $factory->pixelhop(),
        'notehop' => $factory->notehop(),
        'filehop' => $factory->filehop(),
        'payhop' => $factory->payhop(),
        default => $factory->state(['slug' => $slug, 'name' => ucfirst($slug)]),
    };
    $factory->create(['capabilities' => $events ? ['events'] : ['account_api']]);
}

test('an app that listens for events is signed out by the event and is not in the redirect chain', function () {
    Queue::fake();
    foreach (['pixelhop', 'notehop', 'filehop', 'payhop'] as $slug) {
        eventApp($slug);
    }
    // LinkHop and BioHop have no row that listens: they stay in the chain.
    $res = $this->actingAs(User::factory()->create())->post('/logout');

    [$hosts, $end] = logoutWalk($res->headers->get('Location'));
    expect($hosts)->toBe(['link.hel.ink', 'bio.hel.ink'])
        ->and($end)->toBe(route('home'));
});

test('when every app listens for events the browser goes straight to the target', function () {
    Queue::fake();
    foreach (['pixelhop', 'notehop', 'filehop', 'payhop', 'linkhop', 'biohop'] as $slug) {
        eventApp($slug);
    }

    $res = $this->actingAs(User::factory()->create())->post('/logout');

    $res->assertRedirect(route('home'));
});

test('the event is queued exactly once for each app', function () {
    Queue::fake();
    foreach (['pixelhop', 'notehop', 'filehop', 'payhop', 'linkhop', 'biohop'] as $slug) {
        eventApp($slug);
    }
    eventApp('quiet', false);

    $this->actingAs(User::factory()->create())->post('/logout');

    Queue::assertPushed(SendHopEvent::class, 6);
    $ids = Queue::pushed(SendHopEvent::class)->map(fn ($j) => $j->appId)->all();
    expect(array_unique($ids))->toHaveCount(6);
});

test('an app that is not configured is not signed out by the event and is not in the chain', function () {
    Queue::fake();
    HopApp::factory()->payhop('short')->create(['capabilities' => ['events']]);

    $res = $this->actingAs(User::factory()->create())->post('/logout');

    Queue::assertNothingPushed();
    [$hosts] = logoutWalk($res->headers->get('Location'));
    expect($hosts)->not->toContain('pay.example.com');
});

test('when the queue cannot take the event the full redirect chain runs', function () {
    foreach (['pixelhop', 'notehop', 'filehop', 'payhop', 'linkhop', 'biohop'] as $slug) {
        eventApp($slug);
    }
    config()->set('queue.default', 'database');
    Schema::drop('jobs');

    $res = $this->actingAs(User::factory()->create())->post('/logout');

    $this->assertGuest();
    $res->assertRedirect(SsoController::logoutChain(route('home')));
    [$hosts] = logoutWalk($res->headers->get('Location'));
    expect($hosts)->toHaveCount(count(array_filter(HopApps::configured(), fn ($a) => $a->logoutUrl())))
        ->and(count($hosts))->toBeGreaterThanOrEqual(6);
});

test('a sign-out started from an app returns to that app and skips it in the chain', function () {
    Queue::fake();
    foreach (['pixelhop', 'notehop', 'filehop', 'payhop'] as $slug) {
        eventApp($slug);
    }
    $payUrl = HopApps::find('payhop')->url.'/';

    $res = $this->actingAs(User::factory()->create())->get('/logout?return='.urlencode($payUrl));

    $this->assertGuest();
    [$hosts, $end] = logoutWalk($res->headers->get('Location'));
    expect($end)->toBe($payUrl)
        ->and($hosts)->not->toContain(parse_url($payUrl, PHP_URL_HOST));
    Queue::assertPushed(SendHopEvent::class, 4);
});

test('a sign-out from an app after the hub session is gone has no event to rely on and keeps the full chain', function () {
    Queue::fake();
    foreach (['pixelhop', 'notehop', 'filehop', 'payhop'] as $slug) {
        eventApp($slug);
    }
    $payUrl = HopApps::find('payhop')->url.'/';

    $res = $this->get('/logout?return='.urlencode($payUrl));

    Queue::assertNothingPushed();
    $res->assertRedirect(SsoController::logoutChain($payUrl));
    [$hosts, $end] = logoutWalk($res->headers->get('Location'));
    expect($end)->toBe($payUrl)->and($hosts)->toContain('pixel.hel.ink');
});

test('use a different account on the two-factor page signs out the same way', function () {
    Queue::fake();
    foreach (['pixelhop', 'notehop', 'filehop', 'payhop', 'linkhop', 'biohop'] as $slug) {
        eventApp($slug);
    }
    $this->withoutVite();
    $user = User::factory()->create();
    $this->actingAs($user)->get('/two-factor-challenge')->assertOk()->assertSee(route('logout'), false);

    $this->actingAs($user)->post('/logout')->assertRedirect(route('home'));
    Queue::assertPushed(SendHopEvent::class, 6);
});
