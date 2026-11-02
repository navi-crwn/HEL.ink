<?php

use App\Models\User;
use Hop\Auth\Http\Middleware\AuthenticateApiToken;
use Hop\Auth\Models\ApiToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Cache::flush();
    Route::middleware('hop.token')->get('/_test/rate', fn () => response()->json(['ok' => true]));
});

function rateToken(int $limit): string
{
    return ApiToken::generate(User::factory()->create()->id, 'rate', $limit)['plain_token'];
}

test('requests over the limit get a 429 with the real time left in the window', function () {
    $plain = rateToken(2);
    $this->withHeader('Authorization', 'Bearer '.$plain)->getJson('/_test/rate')->assertOk();
    $this->travel(600)->seconds();
    $this->withHeader('Authorization', 'Bearer '.$plain)->getJson('/_test/rate')->assertOk();
    $this->travel(60)->seconds();

    $res = $this->withHeader('Authorization', 'Bearer '.$plain)->getJson('/_test/rate');
    $res->assertStatus(429)->assertJsonPath('error', 'Rate Limit Exceeded');
    $retry = $res->json('retry_after');
    expect($retry)->toBeInt()->toBeLessThan(3600)->toBeGreaterThan(2800)->toBeLessThanOrEqual(2940);
    expect((int) $res->headers->get('Retry-After'))->toBe($retry);
});

test('the window starts at the first request and the counter resets after it', function () {
    $plain = rateToken(1);
    $this->withHeader('Authorization', 'Bearer '.$plain)->getJson('/_test/rate')->assertOk();
    $this->withHeader('Authorization', 'Bearer '.$plain)->getJson('/_test/rate')->assertStatus(429);
    $this->travel(3601)->seconds();
    $this->withHeader('Authorization', 'Bearer '.$plain)->getJson('/_test/rate')->assertOk();
});

test('a token without a usable limit gets the default instead of being locked out', function () {
    $plain = rateToken(0);
    foreach (range(1, 5) as $i) {
        $this->withHeader('Authorization', 'Bearer '.$plain)->getJson('/_test/rate')->assertOk();
    }

    $limitFor = new ReflectionMethod(AuthenticateApiToken::class, 'limitFor');
    $middleware = new AuthenticateApiToken;
    expect($limitFor->invoke($middleware, new ApiToken(['rate_limit' => null])))->toBe(100)
        ->and($limitFor->invoke($middleware, new ApiToken(['rate_limit' => -3])))->toBe(100)
        ->and($limitFor->invoke($middleware, new ApiToken(['rate_limit' => 25])))->toBe(25);
    config()->set('hop-auth.api_rate_limit', 40);
    expect($limitFor->invoke($middleware, new ApiToken(['rate_limit' => null])))->toBe(40);
});

test('the counter is incremented before the check, so the limit holds exactly', function () {
    $plain = rateToken(3);
    $statuses = [];
    foreach (range(1, 6) as $i) {
        $statuses[] = $this->withHeader('Authorization', 'Bearer '.$plain)->getJson('/_test/rate')->status();
    }
    expect($statuses)->toBe([200, 200, 200, 429, 429, 429]);
});

test('tokens are limited separately', function () {
    $a = rateToken(1);
    $b = rateToken(1);
    $this->withHeader('Authorization', 'Bearer '.$a)->getJson('/_test/rate')->assertOk();
    $this->withHeader('Authorization', 'Bearer '.$a)->getJson('/_test/rate')->assertStatus(429);
    $this->withHeader('Authorization', 'Bearer '.$b)->getJson('/_test/rate')->assertOk();
});
