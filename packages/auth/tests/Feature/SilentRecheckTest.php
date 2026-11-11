<?php

use App\Models\User;
use Hop\Auth\Http\Controllers\SsoController;
use Hop\Auth\Http\Middleware\AuthenticateViaHub;
use Hop\Auth\Http\Middleware\RecheckHubSession;
use Hop\Auth\SilentRecheck;
use Hop\Auth\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
 * The silent re-check: a session the hub has not vouched for lately is confirmed with one `prompt=none` round
 * trip on a page load (hub docs: silent-sso.md). One test per branch: fresh, stale, unreachable hub, loop guard,
 * same account, other account, login_required, other errors, bad state.
 */

beforeEach(function () {
    config()->set('hop-auth.secret', 'consumer-secret-that-is-long-enough-123456');
    config()->set('hop-auth.app', 'linkhop');
    config()->set('hop-auth.hub_url', 'https://hub.hel.ink');
    config()->set('hop-auth.recheck', ['enabled' => true, 'max_age' => 300, 'cooldown' => 30, 'retry_after' => 300, 'probe' => true, 'probe_path' => '/up', 'probe_timeout' => 2, 'probe_ttl' => 60]);
    Cache::flush();
    SilentRecheck::$probe = fn () => 200;
});

afterEach(function () {
    SilentRecheck::$probe = null;
    auth()->logout();
});

function recheckRequest(string $method = 'GET', string $uri = '/links?page=2', array $session = [], array $headers = [], ?User $user = null): Request
{
    $request = Request::create($uri, $method);
    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }
    $request->setLaravelSession($store = app('session')->driver('array'));
    foreach ($session as $key => $value) {
        $store->put($key, $value);
    }
    if ($user) {
        auth()->login($user);
        $request->setUserResolver(fn () => $user);
    }

    return $request;
}

function recheckPass(Request $request, string $middleware = AuthenticateViaHub::class): mixed
{
    return (new $middleware)->handle($request, fn () => response('page'));
}

function recheckTicket(array $payload): string
{
    $payload += ['aud' => 'linkhop', 'iat' => time(), 'exp' => time() + 120, 'jti' => bin2hex(random_bytes(8))];
    $body = Ticket::b64(json_encode($payload));

    return $body.'.'.Ticket::b64(hash_hmac('sha256', $body, 'consumer-secret-that-is-long-enough-123456', true));
}

function recheckCallback(Request $request, array $query): Request
{
    foreach ($query as $key => $value) {
        $request->query->set($key, $value);
    }

    return $request;
}

function recheckCallbackStatus(Request $request): int
{
    try {
        (new SsoController)->callback($request);
    } catch (HttpException $e) {
        return $e->getStatusCode();
    }

    return 200;
}

function staleSession(array $extra = []): array
{
    return ['hop_verified_at' => time() - 3600] + $extra;
}

function silentPending(string $next = '/links'): array
{
    return ['hop_sso' => ['state' => 'st', 'next' => $next, 'silent' => true]];
}

test('a fresh session passes without a trip to the hub', function () {
    $request = recheckRequest(session: ['hop_verified_at' => time() - 10], user: User::factory()->create());

    expect(recheckPass($request)->getContent())->toBe('page');
    expect($request->session()->has('hop_sso'))->toBeFalse();
});

test('a stale page load makes one prompt=none trip with state and the registered callback', function () {
    $request = recheckRequest(session: staleSession(), user: User::factory()->create());

    $response = recheckPass($request);
    parse_str((string) parse_url($response->getTargetUrl(), PHP_URL_QUERY), $query);

    expect($response->getTargetUrl())->toStartWith('https://hub.hel.ink/sso/linkhop?');
    expect($query['prompt'])->toBe('none');
    expect($query['return_to'])->toBe(url('/auth/sso/callback'));
    expect(strlen($query['state']))->toBeGreaterThanOrEqual(16)->toBeLessThanOrEqual(128);
    expect($request->session()->get('hop_sso'))->toBe(['state' => $query['state'], 'next' => '/links?page=2', 'silent' => true]);
});

test('a session that never had a timestamp (an older sign-in, a remember cookie) counts as stale', function () {
    $request = recheckRequest(user: User::factory()->create());

    expect(recheckPass($request)->isRedirect())->toBeTrue();
});

test('only a page load re-checks: POST, XHR, JSON and prefetch go through', function (string $method, array $headers) {
    $request = recheckRequest($method, '/links', staleSession(), $headers, User::factory()->create());

    expect(recheckPass($request)->getContent())->toBe('page');
    expect($request->session()->has('hop_sso'))->toBeFalse();
})->with([
    'post' => ['POST', []],
    'put' => ['PUT', []],
    'xhr' => ['GET', ['X-Requested-With' => 'XMLHttpRequest']],
    'json' => ['GET', ['Accept' => 'application/json']],
    'prefetch' => ['GET', ['Purpose' => 'prefetch']],
    'sec-purpose' => ['GET', ['Sec-Purpose' => 'prefetch;prerender']],
]);

test('a hub that does not answer 2xx (502, 503, 404, redirect, timeout) changes nothing: trusted session, no redirect, no sign-out', function (?int $status) {
    SilentRecheck::$probe = fn () => $status;
    $user = User::factory()->create();
    $request = recheckRequest(session: staleSession(['hop_profile' => ['sub' => $user->id]]), user: $user);

    expect(recheckPass($request)->getContent())->toBe('page');
    expect($request->session()->has('hop_sso'))->toBeFalse();
    expect($request->session()->has('hop_profile'))->toBeTrue();
    expect(auth()->id())->toBe($user->id);
})->with(['502' => 502, '503' => 503, '404' => 404, '302' => 302, 'timeout' => null]);

test('the probe asks the hub health url once per window and tries again after it, so a recovered hub is used', function () {
    $calls = [];
    $status = 502;
    SilentRecheck::$probe = function (string $url) use (&$calls, &$status) {
        $calls[] = $url;

        return $status;
    };
    $user = User::factory()->create();

    // Hub down: two page loads, one probe, both pass.
    expect(recheckPass(recheckRequest(session: staleSession(), user: $user))->getContent())->toBe('page');
    expect(recheckPass(recheckRequest(session: staleSession(), user: $user))->getContent())->toBe('page');
    expect($calls)->toBe(['https://hub.hel.ink/up']);

    // The verdict expires, the hub is back: the next page load makes the silent trip.
    Cache::flush();
    $status = 200;
    expect(recheckPass(recheckRequest(session: staleSession(), user: $user))->getTargetUrl())->toContain('prompt=none');
    expect($calls)->toHaveCount(2);
});

test('a hub that is down mid-flow cannot lock the app: the callback never comes, later page loads pass after the cooldown', function () {
    $user = User::factory()->create();
    $first = recheckRequest(session: staleSession(), user: $user);
    expect(recheckPass($first)->isRedirect())->toBeTrue();

    SilentRecheck::$probe = fn () => 502;
    Cache::flush();
    $later = recheckRequest(session: ['hop_recheck_until' => time() - 1] + $first->session()->all(), user: $user);
    expect(recheckPass($later)->getContent())->toBe('page');
});

test('a request that is not a document navigation (img, iframe, script, fetch) is not re-checked', function (string $dest) {
    $request = recheckRequest('GET', '/links', staleSession(), ['Sec-Fetch-Dest' => $dest], User::factory()->create());

    expect(recheckPass($request)->getContent())->toBe('page');
})->with(['image', 'iframe', 'script', 'empty']);

test('at most one attempt per cooldown: a second page load right after goes through', function () {
    $user = User::factory()->create();
    $first = recheckRequest(session: staleSession(), user: $user);
    expect(recheckPass($first)->isRedirect())->toBeTrue();

    $second = recheckRequest(session: $first->session()->all(), user: $user);
    expect(recheckPass($second)->getContent())->toBe('page');

    $later = recheckRequest(session: ['hop_recheck_until' => time() - 1] + $first->session()->all(), user: $user);
    expect(recheckPass($later)->isRedirect())->toBeTrue();
});

test('the check can be turned off', function (array $override) {
    config()->set('hop-auth.recheck', $override + config('hop-auth.recheck'));
    $request = recheckRequest(session: staleSession(), user: User::factory()->create());

    expect(recheckPass($request)->getContent())->toBe('page');
})->with([
    'disabled' => [['enabled' => false]],
    'max_age 0' => [['max_age' => 0]],
]);

test('a guest still goes to the sign-in start, unchanged', function () {
    $response = recheckPass(recheckRequest());

    expect($response->getTargetUrl())->toEndWith('/auth/sso?next='.rawurlencode('/links?page=2'));
});

test('hop.recheck leaves a guest alone and re-checks a signed-in visitor on a public page', function () {
    expect(recheckPass(recheckRequest(), RecheckHubSession::class)->getContent())->toBe('page');

    $request = recheckRequest(session: staleSession(), user: User::factory()->create());
    expect(recheckPass($request, RecheckHubSession::class)->getTargetUrl())->toContain('prompt=none');
});

test('the middleware aliases are registered', function () {
    $aliases = app('router')->getMiddleware();

    expect($aliases['hop.recheck'])->toBe(RecheckHubSession::class)->and($aliases['hop.auth'])->toBe(AuthenticateViaHub::class);
});

test('same account: carries on to the page, refreshes the timestamp, keeps the session id', function () {
    $user = User::factory()->create();
    $request = recheckCallback(recheckRequest('GET', '/auth/sso/callback', silentPending('/links?page=2') + staleSession(), user: $user), [
        'ticket' => recheckTicket(['sub' => (string) $user->id, 'state' => 'st']),
    ]);
    $id = $request->session()->getId();

    $response = (new SsoController)->callback($request);

    expect($response->getTargetUrl())->toEndWith('/links?page=2');
    expect(auth()->id())->toBe($user->id);
    expect($request->session()->getId())->toBe($id);
    expect(time() - $request->session()->get('hop_verified_at'))->toBeLessThan(5);
    expect($request->session()->get('hop_profile')['sub'])->toBe((string) $user->id);
    // Fresh now: the next page load does not ask again.
    expect(recheckPass(recheckRequest(session: $request->session()->all(), user: $user))->getContent())->toBe('page');
});

test('another account: the session switches to the account the hub has', function () {
    $old = User::factory()->create();
    $new = User::factory()->create();
    $request = recheckCallback(recheckRequest('GET', '/auth/sso/callback', silentPending() + staleSession(), user: $old), [
        'ticket' => recheckTicket(['sub' => (string) $new->id, 'state' => 'st']),
    ]);

    $response = (new SsoController)->callback($request);

    expect($response->getTargetUrl())->toEndWith('/links');
    expect(auth()->id())->toBe($new->id);
    expect(time() - $request->session()->get('hop_verified_at'))->toBeLessThan(5);
});

test('another account: nothing of the old session (flags, flash) follows into the new one', function () {
    $old = User::factory()->create();
    $new = User::factory()->create();
    $request = recheckCallback(recheckRequest('GET', '/auth/sso/callback', silentPending() + staleSession(['two_factor_passed' => true, 'draft' => 'x']), user: $old), [
        'ticket' => recheckTicket(['sub' => (string) $new->id, 'state' => 'st']),
    ]);

    (new SsoController)->callback($request);

    expect(auth()->id())->toBe($new->id);
    expect($request->session()->has('two_factor_passed'))->toBeFalse()->and($request->session()->has('draft'))->toBeFalse();
});

test('login_required signs out here and goes on to the page', function () {
    $user = User::factory()->create();
    $request = recheckCallback(recheckRequest('GET', '/auth/sso/callback', silentPending('/pricing') + staleSession(['hop_profile' => ['sub' => $user->id]]), user: $user), [
        'error' => 'login_required',
        'state' => 'st',
    ]);

    $response = (new SsoController)->callback($request);

    expect($response->getTargetUrl())->toEndWith('/pricing');
    expect(auth()->check())->toBeFalse();
    expect($request->session()->has('hop_profile'))->toBeFalse();
    // Nobody is signed in now: a protected page starts the ordinary sign-in, not another silent trip.
    expect(recheckPass(recheckRequest('GET', '/links'))->getTargetUrl())->not->toContain('prompt=none');
});

test('any other hub error keeps the session and waits before asking again', function (string $error) {
    $user = User::factory()->create();
    $request = recheckCallback(recheckRequest('GET', '/auth/sso/callback', silentPending() + staleSession(), user: $user), [
        'error' => $error,
        'state' => 'st',
    ]);

    $response = (new SsoController)->callback($request);

    expect($response->getTargetUrl())->toEndWith('/links');
    expect(auth()->id())->toBe($user->id);
    expect($request->session()->get('hop_recheck_until'))->toBeGreaterThan(time() + 250);
    expect(recheckPass(recheckRequest(session: $request->session()->all(), user: $user))->getContent())->toBe('page');
})->with(['temporarily_unavailable', 'server_error', 'something_new']);

test('an error with the wrong, missing or foreign state is refused and changes nothing', function (array $query, array $pending) {
    $user = User::factory()->create();
    $request = recheckCallback(recheckRequest('GET', '/auth/sso/callback', $pending + staleSession(), user: $user), $query);

    expect(recheckCallbackStatus($request))->toBe(400);
    expect(auth()->id())->toBe($user->id);
})->with([
    'wrong state' => [['error' => 'login_required', 'state' => 'other'], silentPending()],
    'no state' => [['error' => 'login_required'], silentPending()],
    'array state' => [['error' => 'login_required', 'state' => ['st']], silentPending()],
    'nothing pending' => [['error' => 'login_required', 'state' => 'st'], []],
    'not a silent flow' => [['error' => 'login_required', 'state' => 'st'], ['hop_sso' => ['state' => 'st', 'next' => '/x']]],
]);

test('a silent ticket with the wrong state is refused', function () {
    $user = User::factory()->create();
    $request = recheckCallback(recheckRequest('GET', '/auth/sso/callback', silentPending('/x') + staleSession(), user: $user), [
        'ticket' => recheckTicket(['sub' => (string) $user->id, 'state' => 'forged']),
    ]);

    expect(recheckCallbackStatus($request))->toBe(403);
});

test('an ordinary sign-in also vouches for the session', function () {
    $user = User::factory()->create();
    $request = recheckCallback(recheckRequest('GET', '/auth/sso/callback', ['hop_sso' => ['state' => 'st', 'next' => '/links']]), [
        'ticket' => recheckTicket(['sub' => (string) $user->id, 'state' => 'st']),
    ]);

    (new SsoController)->callback($request);

    expect(time() - $request->session()->get('hop_verified_at'))->toBeLessThan(5);
});

test('the plain start route never asks for prompt=none, whatever the query says', function () {
    $request = recheckRequest('GET', '/auth/sso?next=/links&prompt=none');
    $response = (new SsoController)->start($request);

    expect($response->getTargetUrl())->not->toContain('prompt=none');
    expect($request->session()->get('hop_sso'))->not->toHaveKey('silent');
});
