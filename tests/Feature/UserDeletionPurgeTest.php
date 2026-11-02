<?php

use App\Jobs\PurgeAppAccount;
use App\Models\User;
use App\Services\ProxyDetection\ProxyDetectorInterface;
use App\Services\ProxyDetectionService;
use App\Services\UserDeletion;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

/*
 * Account deletion asks LinkHop and BioHop to purge their copy first. A broken
 * app must not block the deletion, but its purge has to be retried, and the
 * failure has to be visible in the log (app name only).
 */

beforeEach(function () {
    config()->set('hop.sso_secret', str_repeat('k', 40));
    config()->set('hop.apps.linkhop.url', 'https://link.hel.ink');
    config()->set('hop.apps.biohop.url', 'https://bio.hel.ink');
});

test('a 200 purge is not retried', function () {
    Queue::fake();
    Http::fake(['*' => Http::response(['ok' => true], 200)]);
    $u = User::factory()->create();

    app(UserDeletion::class)->delete($u);

    Http::assertSentCount(2);
    Queue::assertNotPushed(PurgeAppAccount::class);
    expect(User::find($u->id))->toBeNull();
});

test('a non-2xx purge is logged by app and queued again, the account is still removed', function () {
    Queue::fake();
    Log::spy();
    Http::fake([
        'link.hel.ink/*' => Http::response(['error' => 'down'], 500),
        'bio.hel.ink/*' => Http::response(['ok' => true], 200),
    ]);
    $u = User::factory()->create();

    app(UserDeletion::class)->delete($u);

    Queue::assertPushed(PurgeAppAccount::class, 1);
    Queue::assertPushed(PurgeAppAccount::class, fn ($j) => $j->slug === 'linkhop' && $j->userId === $u->id);
    Log::shouldHaveReceived('warning')->withArgs(fn ($m, $ctx = []) => $m === 'App account purge not accepted'
        && $ctx === ['app' => 'linkhop', 'status' => 500])->once();
    expect(User::find($u->id))->toBeNull();
});

test('a timeout is logged without personal data and queued again', function () {
    Queue::fake();
    Log::spy();
    Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));
    $u = User::factory()->create(['email' => 'rani.secret@example.com']);

    app(UserDeletion::class)->delete($u);

    Queue::assertPushed(PurgeAppAccount::class, 2);
    Log::shouldHaveReceived('warning')->withArgs(function ($m, $ctx = []) {
        return $m === 'App account purge failed' && array_keys($ctx) === ['app', 'reason']
            && ! str_contains(json_encode($ctx), 'rani.secret');
    })->twice();
});

test('the queued job repeats the call and fails (so the queue retries) until the app accepts', function () {
    Http::fakeSequence()->push('nope', 503)->push(['ok' => true], 200);
    $job = new PurgeAppAccount('linkhop', 42, ['qr_codes' => ['qrcodes/a.svg']]);

    expect(fn () => $job->handle())->toThrow(RuntimeException::class);
    $job->handle();

    Http::assertSentCount(2);
    Http::assertSent(fn ($req) => $req->method() === 'DELETE'
        && $req->url() === 'https://link.hel.ink/internal/accounts/42'
        && ! empty($req->header('X-Hop-Signature')[0])
        && str_contains($req->body(), 'qrcodes/a.svg'));
});

test('proxy detection logs a keyed hash, never the address', function () {
    Log::spy();
    $detector = new class implements ProxyDetectorInterface
    {
        public function getName(): string
        {
            return 'stub';
        }

        public function getPriority(): int
        {
            return 1;
        }

        public function isAvailable(): bool
        {
            return true;
        }

        public function detect(string $ip): ?array
        {
            return ['is_proxy' => true, 'type' => 'vpn', 'confidence' => 80];
        }

        public function getDailyLimit(): int
        {
            return PHP_INT_MAX;
        }
    };
    $service = new ProxyDetectionService;
    (fn () => $this->detectors = [$detector])->call($service);

    $service->detect('203.0.113.77');

    Log::shouldHaveReceived('info')->withArgs(function ($m, $ctx = []) {
        return $m === 'Proxy detection result'
            && ! array_key_exists('ip', $ctx)
            && preg_match('/^[0-9a-f]{16}$/', $ctx['ip_hash'] ?? '') === 1
            && ! str_contains(json_encode($ctx), '203.0.113.77');
    })->once();
});
