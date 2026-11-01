<?php

use App\Models\User;
use App\Services\AppSummaries;
use App\Support\HopApp;
use App\Support\HopApps;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
| The numbers on the dashboard's app cards: asked of each app with a signed call,
| cached, and never able to break the page.
*/
const SUM_SECRET = 'a-shared-sso-secret-that-is-long-enough-1234';

function sumApp(string $slug, string $url): HopApp
{
    return HopApp::fromConfig($slug, ['name' => ucfirst($slug), 'url' => $url, 'secret' => SUM_SECRET]);
}

beforeEach(function () {
    Cache::flush();
    config(['hop.summaries' => [
        'linkhop' => ['auth' => 'hub', 'path' => '/internal/accounts/{id}/summary', 'fields' => ['links' => 'Links', 'clicks_7d' => 'Clicks, 7 days']],
        'pixelhop' => ['auth' => 'hop', 'path' => '/hop/users/{id}', 'fields' => ['images' => 'Files', 'storage_bytes' => 'Storage:bytes']],
    ]]);
});

test('numbers come back per app, with the right signature for each kind of app', function () {
    $user = User::factory()->create();
    Http::fake([
        'https://link.example.com/*' => Http::response(['ok' => true, 'data' => ['links' => 12, 'clicks_7d' => 3400]]),
        'https://pixel.example.com/*' => Http::response(['ok' => true, 'data' => ['images' => 5, 'storage_bytes' => 1572864, 'email' => 'x@example.com']]),
    ]);

    $rows = app(AppSummaries::class)->for($user, [sumApp('linkhop', 'https://link.example.com'), sumApp('pixelhop', 'https://pixel.example.com')]);

    expect($rows['linkhop'])->toBe([['label' => 'Links', 'value' => '12'], ['label' => 'Clicks, 7 days', 'value' => '3,400']]);
    expect($rows['pixelhop'])->toBe([['label' => 'Files', 'value' => '5'], ['label' => 'Storage', 'value' => '1.5 MB']]);

    Http::assertSent(function (Request $r) use ($user) {
        if (str_contains($r->url(), 'link.example.com')) {
            $path = "/internal/accounts/{$user->id}/summary";
            $ts = $r->header('X-Hop-Timestamp')[0] ?? '';

            return $r->method() === 'GET' && str_ends_with($r->url(), $path)
                && ($r->header('X-Hop-Signature')[0] ?? '') === hash_hmac('sha256', $ts."\nGET\n".$path."\n".hash('sha256', ''), SUM_SECRET);
        }

        return str_ends_with($r->url(), "/hop/users/{$user->id}") && ($r->header('X-HOP-App')[0] ?? '') === 'helink' && ($r->header('X-HOP-Signature')[0] ?? '') !== '';
    });
});

test('answers are cached, and a failing app is not asked again at once', function () {
    $user = User::factory()->create();
    Http::fakeSequence('https://link.example.com/*')->push(['ok' => true, 'data' => ['links' => 1]])->push(['ok' => true, 'data' => ['links' => 99]]);
    Http::fake(['https://pixel.example.com/*' => Http::response('nope', 503)]);
    $apps = [sumApp('linkhop', 'https://link.example.com'), sumApp('pixelhop', 'https://pixel.example.com')];

    $first = app(AppSummaries::class)->for($user, $apps);
    $second = app(AppSummaries::class)->for($user, $apps);

    expect($first['linkhop'][0]['value'])->toBe('1')->and($second['linkhop'][0]['value'])->toBe('1');
    expect($first)->not->toHaveKey('pixelhop')->and($second)->not->toHaveKey('pixelhop');
    Http::assertSentCount(2);
});

test('an app that is down, unconfigured or has no rule is not called', function () {
    $user = User::factory()->create();
    Http::fake();
    $down = sumApp('linkhop', 'https://link.example.com');
    $other = sumApp('biohop', 'https://bio.example.com');
    $bare = HopApp::fromConfig('pixelhop', ['name' => 'PixelHop', 'url' => 'https://pixel.example.com', 'secret' => 'short']);

    $rows = app(AppSummaries::class)->for($user, [$down, $other, $bare], ['linkhop' => ['ok' => false]]);

    expect($rows)->toBe([]);
    Http::assertNothingSent();
});

test('a malformed or error answer gives no numbers instead of an error page', function () {
    $user = User::factory()->create();
    Http::fake([
        'https://link.example.com/*' => Http::response('<html>oops</html>', 200),
        'https://pixel.example.com/*' => Http::response(['ok' => false, 'error' => 'not_found'], 404),
    ]);

    $rows = app(AppSummaries::class)->for($user, [sumApp('linkhop', 'https://link.example.com'), sumApp('pixelhop', 'https://pixel.example.com')]);

    expect($rows)->toBe([]);
});

test('the dashboard shows the numbers on the card and still renders when an app does not answer', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $slug = collect(HopApps::all())->first(fn ($a) => $a->isConfigured())?->slug;
    if (! $slug) {
        $this->markTestSkipped('No configured app in this environment.');
    }
    config(["hop.summaries.{$slug}" => ['auth' => 'hub', 'path' => '/internal/accounts/{id}/summary', 'fields' => ['links' => 'Links']]]);

    Http::fake(['*/internal/accounts/*' => Http::response(['ok' => true, 'data' => ['links' => 7]])]);
    $this->get('/dashboard')->assertOk()->assertSee('Links')->assertSee('glass-inset', false);

    Cache::flush();
    Http::fake(fn () => throw new ConnectionException('timeout'));
    $this->get('/dashboard')->assertOk()->assertSee('My apps');
});
