<?php

use App\Models\User;
use Hop\Auth\AuthServiceProvider;
use Hop\Auth\HopEvents;
use Hop\Auth\Http\Controllers\EventController;
use Hop\Auth\SessionRevoker;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

const EVENT_SECRET = 'event-secret-that-is-long-enough-1234567';
const EVENT_SECRET_PREV = 'event-secret-previous-long-enough-7654321';

/**
 * Signs a request the way the hub does (HOP App Protocol hop1): written out here on purpose, so
 * the tests do not trust the package to agree with itself.
 */
function eventHeaders(string $body, string $secret = EVENT_SECRET, array $over = []): array
{
    $app = $over['app'] ?? 'helink';
    $ts = (string) ($over['ts'] ?? time());
    $nonce = $over['nonce'] ?? rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    $base = implode('|', ['hop1', $app, $ts, $nonce, 'POST', $over['path'] ?? '/hop/events', hash('sha256', $body)]);
    $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', $base, $secret, true)), '+/', '-_'), '=');

    return [
        'HTTP_X_HOP_APP' => $app,
        'HTTP_X_HOP_TIMESTAMP' => $ts,
        'HTTP_X_HOP_NONCE' => $nonce,
        'HTTP_X_HOP_ACTOR' => 'system',
        'HTTP_X_HOP_SIGNATURE' => $sig,
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ];
}

function sendEvent(array $event, string $secret = EVENT_SECRET, array $over = [])
{
    $body = json_encode($event, JSON_UNESCAPED_SLASHES);

    return test()->call('POST', '/hop/events', [], [], [], eventHeaders($body, $secret, $over), $body);
}

function sessionEvent(int $userId, ?string $id = null): array
{
    return ['id' => $id ?? 'evt-'.bin2hex(random_bytes(6)), 'type' => 'session.revoked', 'at' => now()->toIso8601String(), 'data' => ['sub' => (string) $userId, 'hop_id' => (string) $userId]];
}

beforeEach(function () {
    config()->set('hop-auth.secret', EVENT_SECRET);
    config()->set('hop-auth.secret_prev', null);
    config()->set('session.driver', 'database');
    Cache::flush();
    HopEvents::reset();
    Route::post('/hop/events', EventController::class)->middleware('hop.event')->name('hop.events.test');
});

afterEach(fn () => HopEvents::reset());

function seedSession(string $id, ?int $userId): void
{
    DB::table('sessions')->insert(['id' => $id, 'user_id' => $userId, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => 'x', 'last_activity' => time()]);
}

test('session.revoked deletes every session row of that user and leaves everyone else alone', function () {
    $user = User::factory()->create(['remember_token' => 'old-remember-token']);
    $other = User::factory()->create(['remember_token' => 'other-remember-token']);
    seedSession('s-user-1', $user->id);
    seedSession('s-user-2', $user->id);
    seedSession('s-other', $other->id);
    seedSession('s-guest', null);

    $res = sendEvent(sessionEvent($user->id));

    $res->assertOk()->assertJsonPath('ok', true)->assertJsonPath('data.handled', true);
    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('sessions')->pluck('id')->sort()->values()->all())->toBe(['s-guest', 's-other'])
        ->and($other->fresh()->remember_token)->toBe('other-remember-token');
});

test('session.revoked rotates the remember token so the remember cookie stops working', function () {
    $user = User::factory()->create(['remember_token' => 'old-remember-token']);

    sendEvent(sessionEvent($user->id))->assertOk();

    $token = DB::table('users')->where('id', $user->id)->value('remember_token');
    expect($token)->not->toBe('old-remember-token')->and(strlen((string) $token))->toBe(60);
});

test('with the file driver only the session files of that user are removed', function () {
    $dir = sys_get_temp_dir().'/hop-auth-sessions-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($dir);
    config()->set('session.driver', 'file');
    config()->set('session.files', $dir);
    $user = User::factory()->create();
    $other = User::factory()->create();
    $key = 'login_web_'.sha1(SessionGuard::class);
    file_put_contents("$dir/mine-a", serialize(['_token' => 't', $key => $user->id]));
    file_put_contents("$dir/mine-b", serialize(['_token' => 't', $key => (string) $user->id]));
    file_put_contents("$dir/theirs", serialize(['_token' => 't', $key => $other->id]));
    file_put_contents("$dir/guest", serialize(['_token' => 't']));
    file_put_contents("$dir/junk", 'not a session');

    sendEvent(sessionEvent($user->id))->assertOk();

    expect(array_values(array_diff(scandir($dir), ['.', '..'])))->toBe(['guest', 'junk', 'theirs']);
    File::deleteDirectory($dir);
});

test('encrypted file sessions are found too', function () {
    $dir = sys_get_temp_dir().'/hop-auth-sessions-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($dir);
    config()->set('session.driver', 'file');
    config()->set('session.files', $dir);
    config()->set('session.encrypt', true);
    $user = User::factory()->create();
    $key = 'login_web_'.sha1(SessionGuard::class);
    file_put_contents("$dir/enc", app('encrypter')->encrypt(serialize([$key => $user->id]), false));

    sendEvent(sessionEvent($user->id))->assertOk();

    expect(file_exists("$dir/enc"))->toBeFalse();
    File::deleteDirectory($dir);
});

test('the revoker says when the session driver cannot be searched', function () {
    config()->set('session.driver', 'cookie');
    $user = User::factory()->create(['remember_token' => 'old-remember-token']);

    $result = SessionRevoker::revoke($user->id);

    expect($result['complete'])->toBeFalse()->and($result['driver'])->toBe('cookie')
        ->and($user->fresh()->remember_token)->not->toBe('old-remember-token'); // the cookie that outlives a session is still cut
});

test('account.deleted also drops sessions, even when the users row is already gone', function () {
    seedSession('s-gone', 4242);

    sendEvent(['id' => 'evt-del', 'type' => 'account.deleted', 'data' => ['sub' => '4242', 'hop_id' => '4242', 'email' => 'x@example.com']])
        ->assertOk()->assertJsonPath('data.handled', true);

    expect(DB::table('sessions')->where('user_id', 4242)->count())->toBe(0);
});

test('a bad signature, a stale timestamp, a wrong app and a missing header all get the same bare refusal', function () {
    $user = User::factory()->create();
    seedSession('s-keep', $user->id);
    $event = sessionEvent($user->id);
    $cases = [
        'wrong secret' => sendEvent($event, 'wrong-secret-that-is-long-enough-000000000'),
        'expired' => sendEvent($event, over: ['ts' => time() - 600]),
        'from the future' => sendEvent($event, over: ['ts' => time() + 600]),
        'other app slug' => sendEvent($event, over: ['app' => 'pixelhop']),
        'short nonce' => sendEvent($event, over: ['nonce' => 'abc']),
        'wrong path' => sendEvent($event, over: ['path' => '/api/hop/events']),
    ];
    $unsigned = test()->postJson('/hop/events', $event);
    $bodies = [];
    foreach ($cases as $name => $res) {
        $res->assertStatus(401);
        $bodies[$name] = $res->getContent();
    }
    $unsigned->assertStatus(401);
    $bodies['unsigned'] = $unsigned->getContent();

    expect(array_unique($bodies))->toHaveCount(1)
        ->and(json_decode(reset($bodies), true))->toBe(['ok' => false, 'error' => 'unauthorized']);
    expect(DB::table('sessions')->where('id', 's-keep')->exists())->toBeTrue();
});

test('a body changed after signing is refused', function () {
    $user = User::factory()->create();
    seedSession('s-keep', $user->id);
    $signed = json_encode(sessionEvent($user->id, 'evt-orig'));
    $headers = eventHeaders($signed);

    test()->call('POST', '/hop/events', [], [], [], $headers, json_encode(sessionEvent($user->id, 'evt-forged')))->assertStatus(401);

    expect(DB::table('sessions')->where('id', 's-keep')->exists())->toBeTrue();
});

test('replaying the same signed request is refused and does not run the handler twice', function () {
    $calls = 0;
    HopEvents::on('session.revoked', function () use (&$calls) {
        $calls++;
    });
    $user = User::factory()->create();
    $body = json_encode(sessionEvent($user->id, 'evt-replay'));
    $headers = eventHeaders($body);

    test()->call('POST', '/hop/events', [], [], [], $headers, $body)->assertOk();
    test()->call('POST', '/hop/events', [], [], [], $headers, $body)->assertStatus(401);

    expect($calls)->toBe(1);
});

test('the same event id sent again with a fresh signature is acknowledged once and not handled again', function () {
    $calls = 0;
    HopEvents::on('session.revoked', function () use (&$calls) {
        $calls++;
    });
    $user = User::factory()->create();
    $event = sessionEvent($user->id, 'evt-retry');

    sendEvent($event)->assertOk()->assertJsonPath('data.handled', true);
    sendEvent($event)->assertOk()->assertJsonPath('data.duplicate', true)->assertJsonPath('data.handled', false);

    expect($calls)->toBe(1);
});

test('a request signed with the previous secret is accepted during rotation; an unknown secret is not', function () {
    config()->set('hop-auth.secret_prev', EVENT_SECRET_PREV);
    $user = User::factory()->create();
    seedSession('s-rot', $user->id);

    sendEvent(sessionEvent($user->id), EVENT_SECRET_PREV)->assertOk();
    expect(DB::table('sessions')->where('id', 's-rot')->exists())->toBeFalse();

    sendEvent(sessionEvent($user->id), 'never-configured-secret-long-enough-111111')->assertStatus(401);
    config()->set('hop-auth.secret_prev', null);
    sendEvent(sessionEvent($user->id), EVENT_SECRET_PREV)->assertStatus(401);
});

test('with no secret configured every event is refused the same way', function () {
    config()->set('hop-auth.secret', '');

    sendEvent(sessionEvent(1))->assertStatus(401)->assertExactJson(['ok' => false, 'error' => 'unauthorized']);
});

test('an event type this app does not know gets a 200 so the hub stops retrying', function () {
    sendEvent(['id' => 'evt-new', 'type' => 'something.new', 'data' => ['x' => 1]])
        ->assertOk()->assertJsonPath('ok', true)->assertJsonPath('data.handled', false);
    sendEvent(['id' => 'evt-key', 'type' => 'app.secret_rotated', 'data' => []])->assertOk()->assertJsonPath('data.handled', false);
});

test('an event with no id or type, or a data value that is not an object, is a 400 once the signature has passed', function () {
    sendEvent(['type' => 'session.revoked', 'data' => ['sub' => '1']])->assertStatus(400)->assertJsonPath('error', 'bad_request');
    sendEvent(['id' => 'evt-x', 'data' => []])->assertStatus(400);
    sendEvent(['id' => 'evt-y', 'type' => 'session.revoked', 'data' => 'nope'])->assertStatus(400);
    sendEvent(['id' => ['a'], 'type' => 'session.revoked'])->assertStatus(400);
});

test('a session.revoked with no usable account id is acknowledged but changes nothing', function () {
    $user = User::factory()->create();
    seedSession('s-keep', $user->id);

    sendEvent(['id' => 'evt-noid', 'type' => 'session.revoked', 'data' => []])->assertOk()->assertJsonPath('data.handled', false);
    sendEvent(['id' => 'evt-bad', 'type' => 'session.revoked', 'data' => ['sub' => 'abc']])->assertOk()->assertJsonPath('data.handled', false);
    sendEvent(['id' => 'evt-arr', 'type' => 'session.revoked', 'data' => ['sub' => ['1']]])->assertOk()->assertJsonPath('data.handled', false);

    expect(DB::table('sessions')->where('id', 's-keep')->exists())->toBeTrue();
});

test('apps hook in with HopEvents::on and get the data and the whole event', function () {
    $seen = [];
    HopEvents::on('account.status', function (array $data, array $event) use (&$seen) {
        $seen = [$data, $event['id']];
    });

    sendEvent(['id' => 'evt-status', 'type' => 'account.status', 'data' => ['sub' => '9', 'hop_id' => '9', 'status' => 'suspended']])
        ->assertOk()->assertJsonPath('data.handled', true);

    expect($seen)->toBe([['sub' => '9', 'hop_id' => '9', 'status' => 'suspended'], 'evt-status']);
});

test('account.status has no built-in action: the app decides what a suspension means', function () {
    $user = User::factory()->create();
    seedSession('s-keep', $user->id);

    sendEvent(['id' => 'evt-s2', 'type' => 'account.status', 'data' => ['sub' => (string) $user->id, 'status' => 'suspended']])
        ->assertOk()->assertJsonPath('data.handled', false);

    expect(DB::table('sessions')->where('id', 's-keep')->exists())->toBeTrue();
});

test('a handler that throws makes the hub retry: 500, and the event id is not burned', function () {
    $fail = true;
    HopEvents::on('profile.updated', function () use (&$fail) {
        if ($fail) {
            throw new RuntimeException('database is down');
        }
    });
    $event = ['id' => 'evt-flaky', 'type' => 'profile.updated', 'data' => ['sub' => '1']];

    $first = sendEvent($event);
    $first->assertStatus(500)->assertJsonPath('error', 'failed');
    expect($first->getContent())->not->toContain('database is down');

    $fail = false;
    sendEvent($event)->assertOk()->assertJsonPath('data.handled', true);
});

test('HopEvents::forget drops the built-in handler for an app that wants none', function () {
    HopEvents::forget('session.revoked');
    $user = User::factory()->create();
    seedSession('s-keep', $user->id);

    sendEvent(sessionEvent($user->id))->assertOk()->assertJsonPath('data.handled', false);

    expect(DB::table('sessions')->where('id', 's-keep')->exists())->toBeTrue();
});

test('the receiver route is off until the app names a path for it', function () {
    expect(config('hop-auth.routes.events'))->toBeNull();
    expect(collect(Route::getRoutes()->getRoutes())->contains(fn ($r) => $r->getName() === 'hop.events'))->toBeFalse();

    config()->set('hop.current', 'linkhop'); // the hub never mounts the receiver; this test is about an app
    config()->set('hop-auth.routes.events', '/hooks/hop');
    $this->app->register(AuthServiceProvider::class, force: true);
    Route::getRoutes()->refreshNameLookups();

    $route = Route::getRoutes()->getByName('hop.events');
    // Not in the web group: no session, cookie or CSRF token for a server-to-server call.
    expect($route?->uri())->toBe('hooks/hop')->and($route->methods())->toBe(['POST'])->and($route->gatherMiddleware())->toBe(['hop.event']);
});
