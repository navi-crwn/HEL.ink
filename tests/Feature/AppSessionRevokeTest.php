<?php

use App\Models\HopApp;
use App\Models\User;
use App\Support\EventRegistry;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

/*
 * H5 (2026-11-10): signing out, and so switching accounts, reaches every app with the `events` capability,
 * FileHop included, with the id of the account that was signed in. The queue is sync in tests.
 */

/** @return list<array> the JSON bodies POSTed to one app's /hop/events */
function eventsSentTo(string $host): array
{
    $out = [];
    foreach (Http::recorded() as [$request]) {
        if (str_starts_with($request->url(), $host.'/hop/events')) {
            $out[] = $request->data();
        }
    }

    return $out;
}

test('signing out sends a signed session.revoked to FileHop and the other apps', function () {
    Http::fake(['*' => Http::response(['ok' => true])]);
    HopApp::factory()->filehop()->create();
    HopApp::factory()->pixelhop()->create(['capabilities' => ['events']]);
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect();

    $sent = eventsSentTo('https://file.example.com');
    expect($sent)->toHaveCount(1);
    expect($sent[0]['type'])->toBe('session.revoked');
    expect($sent[0]['data'])->toBe(['sub' => (string) $user->id, 'hop_id' => (string) $user->id]);
    expect(eventsSentTo('https://p.example.com'))->toHaveCount(1);
    Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://file.example.com/hop/events') && $r->hasHeader('X-HOP-Signature'));
});

test('switching accounts revokes each account that signed out and never the one that just signed in', function () {
    Http::fake(['*' => Http::response(['ok' => true])]);
    HopApp::factory()->filehop()->create();
    config()->set('services.turnstile.secret', null);
    $a = User::factory()->create();
    $b = User::factory()->create(['password' => Hash::make('password')]);
    $subs = fn () => array_column(array_column(eventsSentTo('https://file.example.com'), 'data'), 'sub');

    $this->actingAs($a)->post('/logout')->assertRedirect();
    $this->assertGuest();
    expect($subs())->toBe([(string) $a->id]);

    $this->post('/login', ['email' => $b->email, 'password' => 'password']);
    $this->assertAuthenticatedAs($b);
    expect($subs())->toBe([(string) $a->id]);   // signing in revokes nothing

    $this->post('/logout');
    expect($subs())->toBe([(string) $a->id, (string) $b->id]);
});

test('a FileHop row without the events capability is reported, and a new row gets it', function () {
    HopApp::factory()->filehop()->create(['capabilities' => ['admin_api']]);
    expect(implode("\n", EventRegistry::warnings()))->toContain('filehop')->toContain('events');
    expect(EventRegistry::capabilitiesFor('filehop'))->toContain('events');
});
