<?php

use App\Models\User;
use Hop\Auth\Http\Controllers\SsoController;
use Hop\Auth\Ticket;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
 * Array-shaped query values (?next[]=x, ?ticket[]=x, ?next[a]=b) are not strings. The controller used to cast
 * them with (string), which raises "Array to string conversion" and answers 500 on every SSO entry point.
 */

beforeEach(function () {
    config()->set('hop-auth.secret', 'consumer-secret-that-is-long-enough-123456');
    config()->set('hop-auth.app', 'linkhop');
    config()->set('hop-auth.hub_url', 'https://hub.hel.ink');
});

function ssoRequest(string $path, array $query, array $session = []): Request
{
    $request = Request::create($path, 'GET', $query);
    $request->setLaravelSession($store = app('session')->driver('array'));
    foreach ($session as $key => $value) {
        $store->put($key, $value);
    }

    return $request;
}

function ssoTicket(array $payload): string
{
    $body = Ticket::b64(json_encode($payload));

    return $body.'.'.Ticket::b64(hash_hmac('sha256', $body, 'consumer-secret-that-is-long-enough-123456', true));
}

function ssoStatus(Closure $call): int
{
    try {
        $call();
    } catch (HttpException $e) {
        return $e->getStatusCode();
    }

    return 200;
}

dataset('array query shapes', [
    'empty brackets' => [['x']],
    'keyed' => [['a' => 'b']],
    'nested' => [['a' => ['b' => ['c']]]],
]);

test('start treats an array next as absent and still redirects to the hub', function (array $next) {
    $request = ssoRequest('/auth/sso', ['next' => $next]);
    $response = (new SsoController)->start($request);

    expect($response->getTargetUrl())->toStartWith('https://hub.hel.ink/sso/linkhop?state=');
    expect($request->session()->get('hop_sso.next'))->toBe('');
})->with('array query shapes');

test('start ignores unrelated array parameters such as state[] and keeps a normal next', function () {
    $request = ssoRequest('/auth/sso', ['next' => '/dashboard', 'state' => ['x'], 'a' => ['b' => 'c']]);
    $response = (new SsoController)->start($request);

    expect($response->getTargetUrl())->toStartWith('https://hub.hel.ink/sso/linkhop?state=');
    expect($request->session()->get('hop_sso.next'))->toBe('/dashboard');
    expect($request->session()->get('hop_sso.state'))->toHaveLength(40);
});

test('callback answers 400 for an array ticket, not 500', function (array $ticket) {
    $request = ssoRequest('/auth/sso/callback', ['ticket' => $ticket], ['hop_sso' => ['state' => 'st', 'next' => '/dashboard']]);

    expect(ssoStatus(fn () => (new SsoController)->callback($request)))->toBe(400);
})->with('array query shapes');

test('callback still verifies a string ticket when other parameters are arrays', function () {
    $request = ssoRequest(
        '/auth/sso/callback',
        ['ticket' => 'garbage', 'state' => ['x']],
        ['hop_sso' => ['state' => 'st', 'next' => '/dashboard']],
    );

    expect(ssoStatus(fn () => (new SsoController)->callback($request)))->toBe(403);
});

test('callback signs a user in with a valid ticket and ignores a state[] parameter', function () {
    $user = User::factory()->create();
    $ticket = ssoTicket(['sub' => $user->id, 'aud' => 'linkhop', 'state' => 'st', 'iat' => time(), 'exp' => time() + 120, 'jti' => bin2hex(random_bytes(8))]);
    $request = ssoRequest(
        '/auth/sso/callback',
        ['ticket' => $ticket, 'state' => ['x']],
        ['hop_sso' => ['state' => 'st', 'next' => '/links']],
    );

    $response = (new SsoController)->callback($request);

    expect($response->getTargetUrl())->toEndWith('/links');
    expect(auth()->id())->toBe($user->id);
});

test('logout treats an array next as absent and goes home', function (array $next) {
    $request = ssoRequest('/auth/sso/logout', ['next' => $next]);
    $response = (new SsoController)->logout($request);

    expect($response->getTargetUrl())->toBe(url('/'));
})->with('array query shapes');

test('logout still follows a trusted next when other parameters are arrays', function () {
    $request = ssoRequest('/auth/sso/logout', ['next' => 'https://bio.hel.ink/auth/sso/logout', 'x' => ['y']]);

    expect((new SsoController)->logout($request)->getTargetUrl())->toBe('https://bio.hel.ink/auth/sso/logout');
});
