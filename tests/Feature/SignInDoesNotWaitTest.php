<?php

use App\Jobs\EnrichLoginRecord;
use App\Models\LoginHistory;
use App\Models\User;
use App\Services\GeoIpService;
use App\Services\LoginRecorder;
use App\Services\ProxyDetectionService;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

/*
 * A sign-in does not wait for GeoIP or proxy lookups: the history row is written at once and the
 * lookups run from the queue (or after the response when the queue is down).
 */

test('a password sign-in completes with every outside call failing, and sends no lookup before the response', function () {
    Queue::fake();
    Http::fake(fn () => throw new RuntimeException('network is down'));
    $user = User::factory()->create();

    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Firefox/120.0'])
        ->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
    Http::assertNothingSent();
    Queue::assertPushed(EnrichLoginRecord::class, 1);
    $row = LoginHistory::where('user_id', $user->id)->signIns()->first();
    expect($row)->not->toBeNull()
        ->and($row->ip_address)->toBe('127.0.0.1')
        ->and($row->browser)->not->toBeNull()
        ->and($user->fresh()->last_login_ip)->toBe('127.0.0.1');
});

test('the lookups that run later fill in the row', function () {
    $user = User::factory()->create();
    $row = LoginHistory::create(['user_id' => $user->id, 'kind' => 'login', 'ip_address' => '203.0.113.9', 'logged_in_at' => now()]);
    $this->mock(GeoIpService::class, fn ($m) => $m->shouldReceive('details')->andReturn([
        'country' => 'ID', 'country_name' => 'Indonesia', 'city' => 'Jakarta', 'region' => 'JK', 'provider' => 'Telkomsel', 'isp' => 'Telkom',
    ]));
    $this->mock(ProxyDetectionService::class, fn ($m) => $m->shouldReceive('detect')->andReturn(['is_proxy' => false, 'type' => null, 'confidence' => 0]));

    (new EnrichLoginRecord($row->id, $user->id, '203.0.113.9', 'password'))->handle(app(GeoIpService::class), app(ProxyDetectionService::class));

    expect($row->fresh()->city)->toBe('Jakarta')->and($row->fresh()->provider)->toBe('Telkomsel')
        ->and($user->fresh()->last_login_city)->toBe('Jakarta');
});

test('a Google sign-in and an app hand-off are recorded at once and queued the same way', function () {
    Queue::fake();
    Http::fake(fn () => throw new RuntimeException('network is down'));
    $user = User::factory()->create();

    app(LoginRecorder::class)->record($user, '203.0.113.5', 'Mozilla/5.0', 'google');
    app(LoginRecorder::class)->record($user, '203.0.113.5', 'Mozilla/5.0', 'PayHop');

    Http::assertNothingSent();
    Queue::assertPushed(EnrichLoginRecord::class, 2);
    expect(LoginHistory::where('user_id', $user->id)->signIns()->value('provider'))->toBe('google')
        ->and(LoginHistory::where('user_id', $user->id)->handoffs()->value('provider'))->toBe('PayHop');
});

test('when the queue cannot take the job the lookups run once after the response, not before', function () {
    config()->set('queue.default', 'database');
    Schema::drop('jobs');
    $calls = 0;
    $this->mock(GeoIpService::class, function ($m) use (&$calls) {
        $m->shouldReceive('details')->andReturnUsing(function () use (&$calls) {
            $calls++;

            return ['country' => 'ID', 'country_name' => 'Indonesia', 'city' => 'Bandung', 'region' => 'JB', 'provider' => 'X', 'isp' => 'Y'];
        });
    });
    $this->mock(ProxyDetectionService::class, fn ($m) => $m->shouldReceive('detect')->andReturn(['is_proxy' => false, 'type' => null, 'confidence' => 0]));
    $user = User::factory()->create();

    app(LoginRecorder::class)->record($user, '203.0.113.5', 'Mozilla/5.0', 'password');

    expect($calls)->toBe(0)
        ->and(LoginHistory::where('user_id', $user->id)->value('city'))->toBeNull();
    app(DeferredCallbackCollection::class)->invoke();
    expect($calls)->toBe(1)
        ->and(LoginHistory::where('user_id', $user->id)->value('city'))->toBe('Bandung');
});

test('a lookup job that fails on every attempt is dropped, not kept in failed_jobs, and the log has no address', function () {
    config()->set('queue.default', 'database');
    $this->mock(GeoIpService::class, fn ($m) => $m->shouldReceive('details')->andThrow(new RuntimeException('provider said 203.0.113.77 is bad')));
    $user = User::factory()->create();
    Log::spy();

    app(LoginRecorder::class)->record($user, '203.0.113.77', 'Mozilla/5.0', 'password');
    expect(DB::table('jobs')->count())->toBe(1);

    $this->artisan('queue:work', ['--once' => true, '--tries' => 3]);
    expect(DB::table('jobs')->count())->toBe(1);
    $this->travel(31)->seconds();
    $this->artisan('queue:work', ['--once' => true, '--tries' => 3]);
    expect(DB::table('jobs')->count())->toBe(1);
    $this->travel(301)->seconds();
    $this->artisan('queue:work', ['--once' => true, '--tries' => 3]);

    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0)
        ->and(LoginHistory::where('user_id', $user->id)->exists())->toBeTrue();
    Log::shouldHaveReceived('warning')->withArgs(fn ($msg, $ctx = []) => str_contains($msg, 'dropped')
        && ! str_contains($msg.json_encode($ctx), '203.0.113.77'))->once();
});
