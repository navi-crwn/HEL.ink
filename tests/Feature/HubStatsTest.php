<?php

use App\Models\GithubStat;
use App\Models\ServiceCheck;
use App\Support\GithubStats;
use App\Support\HopApps;
use App\Support\UptimeStats;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    config()->set('hop.github.token', null);
    config()->set('hop.github.repos', ['navi-crwn/HEL.ink', 'navi-crwn/LinkHop']);
    Cache::flush();
});

/**
 * A GitHub that answers like the real one for every repo. $mode changes one behaviour:
 * 'etag' answers 304 to If-None-Match, 'limit' answers 403, 'missing:Repo' 404s that repo,
 * 'computing' answers 202 for the weekly stats, 'notag' has no release (tags instead).
 *
 * @return list<Request> filled with every request sent
 */
function fakeGithub(string $mode = 'ok', array &$sent = []): void
{
    Http::swap(new Factory);   // a new fake replaces the old one instead of queueing behind it
    Http::fake(function (Request $request) use ($mode, &$sent) {
        $sent[] = $request;
        $url = $request->url();
        preg_match('#/repos/navi-crwn/([^/?]+)(/[^?]*)?#', $url, $m);
        $repo = $m[1] ?? '';
        $path = $m[2] ?? '';
        if ($mode === 'limit') {
            return Http::response(['message' => 'API rate limit exceeded'], 403, ['X-RateLimit-Remaining' => '0']);
        }
        if ($mode === 'missing:'.$repo) {
            return Http::response(['message' => 'Not Found'], 404);
        }
        $etag = '"etag-'.md5($url).'"';
        if ($mode === 'etag' && $request->hasHeader('If-None-Match')) {
            return Http::response('', 304, ['ETag' => $etag]);
        }
        $headers = ['ETag' => $etag];
        $page = fn (int $last) => ['Link' => '<https://api.github.com/repositories/1'.$path.'?per_page=1&page=2>; rel="next", <https://api.github.com/repositories/1'.$path.'?per_page=1&page='.$last.'>; rel="last"'];

        return match ($path) {
            '' => Http::response(['created_at' => '2026-10-01T08:00:00Z', 'pushed_at' => '2026-11-05T09:30:00Z', 'stargazers_count' => 42], 200, $headers),
            '/languages' => Http::response(['PHP' => 6000, 'Blade' => 3000, 'CSS' => 1000], 200, $headers),
            '/stats/participation' => $mode === 'computing'
                ? Http::response('', 202)
                : Http::response(['all' => array_merge(array_fill(0, 51, 2), [10]), 'owner' => []], 200, $headers),
            '/commits' => Http::response([['sha' => 'a']], 200, $headers + $page(345)),
            '/contributors' => Http::response([['login' => 'someone']], 200, $headers + $page(7)),
            '/releases' => Http::response([['tag_name' => 'v1.2.3']], 200, $headers + $page(12)),
            '/releases/latest' => $mode === 'notag'
                ? Http::response(['message' => 'Not Found'], 404)
                : Http::response(['tag_name' => 'v1.2.3', 'published_at' => '2026-11-01T10:00:00Z'], 200, $headers),
            '/tags' => Http::response([['name' => 'v0.9.0']], 200, $headers),
            default => Http::response('', 404),
        };
    });
}

test('hop:github-stats stores the numbers of every configured repo', function () {
    fakeGithub();
    $this->artisan('hop:github-stats')->assertSuccessful();

    $all = app(GithubStats::class)->all();
    expect($all['repos'])->toHaveCount(2)->and($all['fetched_at'])->not->toBeNull();
    $repo = $all['repos'][0];
    expect($repo['repo'])->toBe('navi-crwn/HEL.ink')->and($repo['name'])->toBe('HEL.ink')
        ->and($repo['stars'])->toBe(42)->and($repo['commits'])->toBe(345)->and($repo['contributors'])->toBe(7)
        ->and($repo['releases'])->toBe(12)->and($repo['version'])->toBe('v1.2.3')
        ->and($repo['version_date'])->toStartWith('2026-11-01')->and($repo['created_at'])->toStartWith('2026-10-01')
        ->and($repo['pushed_at'])->toStartWith('2026-11-05')
        ->and($repo['languages'])->toBe([['name' => 'PHP', 'percent' => 60.0], ['name' => 'Blade', 'percent' => 30.0], ['name' => 'CSS', 'percent' => 10.0]])
        ->and($repo['weeks'])->toHaveCount(52)->and($repo['weeks'][51])->toBe(10);
    expect(GithubStat::count())->toBe(2);
});

test('a second run sends the stored ETags and a 304 keeps the numbers', function () {
    fakeGithub();
    $this->artisan('hop:github-stats')->assertSuccessful();
    $before = app(GithubStats::class)->all();

    $sent = [];
    fakeGithub('etag', $sent);
    $this->artisan('hop:github-stats')->expectsOutputToContain('unchanged')->assertSuccessful();

    expect($sent)->not->toBeEmpty();
    foreach ($sent as $request) {
        expect($request->hasHeader('If-None-Match'))->toBeTrue();
    }
    $after = app(GithubStats::class)->all();
    expect($after['repos'])->toEqual($before['repos']);
});

test('a rate limit keeps the old numbers and stops the run', function () {
    fakeGithub();
    $this->artisan('hop:github-stats')->assertSuccessful();
    $before = app(GithubStats::class)->all();

    $sent = [];
    fakeGithub('limit', $sent);
    $this->artisan('hop:github-stats')->expectsOutputToContain('rate-limited')->assertSuccessful();

    expect($sent)->toHaveCount(1);   // the first request was refused, nothing else is tried
    expect(app(GithubStats::class)->all())->toEqual($before);
});

test('a missing repo is skipped and the others are still stored', function () {
    fakeGithub('missing:LinkHop');
    $this->artisan('hop:github-stats')->expectsOutputToContain('missing')->assertSuccessful();

    $repos = app(GithubStats::class)->all()['repos'];
    expect($repos)->toHaveCount(1)->and($repos[0]['name'])->toBe('HEL.ink');
});

test('GitHub still computing the weekly stats (202) keeps the old weeks', function () {
    fakeGithub('computing');
    $this->artisan('hop:github-stats')->assertSuccessful();
    expect(app(GithubStats::class)->all()['repos'][0]['weeks'])->toBeNull();

    fakeGithub();
    $this->artisan('hop:github-stats')->assertSuccessful();
    expect(app(GithubStats::class)->all()['repos'][0]['weeks'])->toHaveCount(52);

    fakeGithub('computing');
    $this->artisan('hop:github-stats')->assertSuccessful();
    expect(app(GithubStats::class)->all()['repos'][0]['weeks'])->toHaveCount(52);
});

test('without a release the newest tag is the version', function () {
    fakeGithub('notag');
    $this->artisan('hop:github-stats')->assertSuccessful();
    $repo = app(GithubStats::class)->all()['repos'][0];
    expect($repo['version'])->toBe('v0.9.0')->and($repo['version_date'])->toBeNull();
});

test('a network failure never throws and keeps what was stored', function () {
    fakeGithub();
    $this->artisan('hop:github-stats')->assertSuccessful();
    $before = app(GithubStats::class)->all();

    Http::swap(new Factory);
    Http::fake(fn () => throw new ConnectionException('timeout'));
    $this->artisan('hop:github-stats')->expectsOutputToContain('failed')->assertSuccessful();
    expect(app(GithubStats::class)->all())->toEqual($before);
});

test('the optional token is sent to GitHub only and never shows up anywhere else', function () {
    config()->set('hop.github.token', 'ghp_secret_token_value');
    $sent = [];
    fakeGithub('ok', $sent);
    Artisan::call('hop:github-stats');
    expect(Artisan::output())->not->toContain('ghp_secret_token_value');
    expect($sent)->not->toBeEmpty();
    foreach ($sent as $request) {
        expect($request->header('Authorization'))->toBe(['Bearer ghp_secret_token_value']);
        expect($request->url())->toStartWith('https://api.github.com/');
    }

    $payloads = GithubStat::all()->map(fn ($r) => json_encode([$r->payload, $r->etags]))->implode('');
    expect($payloads)->not->toContain('ghp_secret_token_value');
    expect($this->get('/stats/github.json')->getContent())->not->toContain('ghp_secret_token_value');
    expect(view('partials.home-open')->render())->not->toContain('ghp_secret_token_value');

    // and without a token no Authorization header is sent at all
    config()->set('hop.github.token', null);
    $sent = [];
    fakeGithub('ok', $sent);
    Artisan::call('hop:github-stats');
    expect($sent[0]->hasHeader('Authorization'))->toBeFalse();
});

test('hop:github-stats runs hourly without overlapping', function () {
    Artisan::call('schedule:list');
    expect(Artisan::output())->toContain('hop:github-stats');
    $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'hop:github-stats'));
    expect($event->expression)->toBe('0 * * * *')->and($event->withoutOverlapping)->toBeTrue();
});

test('/stats/github.json is numbers only, publicly cacheable, and calm when empty', function () {
    $empty = $this->get('/stats/github.json')->assertOk()->assertHeader('Cache-Control', 'max-age=300, public');
    expect($empty->json())->toBe(['fetched_at' => null, 'repos' => []]);

    fakeGithub();
    $this->artisan('hop:github-stats')->assertSuccessful();
    $json = $this->get('/stats/github.json')->assertOk()->json();
    expect(array_keys($json))->toBe(['fetched_at', 'repos']);
    expect($json['fetched_at'])->toBeString();
    expect(array_keys($json['repos'][0]))->toBe(['repo', 'name', 'url', 'created_at', 'pushed_at', 'stars', 'commits', 'releases', 'contributors', 'version', 'version_date', 'languages', 'weeks', 'fetched_at']);
    expect(array_keys($json['repos'][0]['languages'][0]))->toBe(['name', 'percent']);
});

test('/stats/uptime.json: the percentage and 30 daily buckets come from the watchdog probes', function () {
    foreach (['biohop', 'pixelhop', 'notehop', 'filehop'] as $slug) {
        config()->set("hop.apps.$slug.enabled", false);
    }
    config()->set('hop.apps.linkhop.url', 'https://link.example.com');
    $this->travelTo('2026-11-06 12:00:00');
    $probe = fn (string $at, bool $ok, string $kind = 'health') => ServiceCheck::create(['app_slug' => 'linkhop', 'kind' => $kind, 'ok' => $ok, 'checked_at' => $at]);
    foreach (range(1, 3) as $i) {
        $probe("2026-11-06 0$i:00:00", true);      // today: 3 of 4 answered = 75%
    }
    $probe('2026-11-06 04:00:00', false);
    foreach (range(1, 4) as $i) {
        $probe("2026-11-04 0$i:00:00", true);      // two days ago: 100%
    }
    $probe('2026-11-05 05:00:00', false, 'check');   // another kind of probe does not count
    $probe('2026-09-20 05:00:00', false);            // older than 30 days does not count

    $json = $this->get('/stats/uptime.json')->assertOk()->assertHeader('Cache-Control', 'max-age=300, public')->json();

    expect(array_keys($json))->toBe(['fetched_at', 'days', 'apps'])->and($json['days'])->toBe(30)->and($json['apps'])->toHaveCount(1);
    $app = $json['apps'][0];
    expect(array_keys($app))->toBe(['slug', 'name', 'uptime', 'days_measured', 'days']);
    expect($app['slug'])->toBe('linkhop')->and($app['uptime'])->toBe(87.5)->and($app['days_measured'])->toBe(2)->and($app['days'])->toHaveCount(30);
    expect(array_keys($app['days'][0]))->toBe(['date', 'status', 'uptime']);
    $byDate = collect($app['days'])->keyBy('date');
    expect($byDate['2026-11-06']['status'])->toBe('down')->and($byDate['2026-11-06']['uptime'])->toEqual(75)
        ->and($byDate['2026-11-04']['status'])->toBe('up')->and($byDate['2026-11-04']['uptime'])->toEqual(100)
        ->and($byDate['2026-11-05']['status'])->toBe('none')->and($byDate['2026-11-05']['uptime'])->toBeNull()
        ->and($app['days'][0]['date'])->toBe('2026-10-08')->and($app['days'][29]['date'])->toBe('2026-11-06');
});

test('an app with no probes yet has no percentage, not 0', function () {
    config()->set('hop.apps.linkhop.url', 'https://link.example.com');
    $apps = app(UptimeStats::class)->all()['apps'];
    expect($apps)->not->toBeEmpty();
    foreach ($apps as $app) {
        expect($app['uptime'])->toBeNull()->and($app['days_measured'])->toBe(0);
        expect(collect($app['days'])->pluck('status')->unique()->all())->toBe(['none']);
    }
    expect(app(UptimeStats::class)->all()['fetched_at'])->toBeNull();
});

test('the watchdog keeps a month of probes for the uptime strip', function () {
    expect(config('hop.watch.keep_days'))->toBeGreaterThanOrEqual(30);
});

test('the home section renders on the server with the stored numbers and sends no request', function () {
    fakeGithub();
    $this->artisan('hop:github-stats')->assertSuccessful();
    config()->set('hop.apps.linkhop.url', 'https://link.example.com');
    ServiceCheck::create(['app_slug' => 'linkhop', 'kind' => 'health', 'ok' => true, 'checked_at' => now()->subHour()]);
    Http::swap(new Factory);
    Http::fake();

    // inside the real layout, the way home.blade.php includes it: the assets land in <head>
    $page = Blade::render('<x-marketing-layout :themed="true">@include("partials.home-open")</x-marketing-layout>');
    expect(Str::before($page, '</head>'))->toContain('/css/home-open.css')->toContain('/js/home-open.js');
    $html = view('partials.home-open')->render();

    Http::assertNothingSent();
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//*[@role="tablist"]')->length)->toBe(1);
    expect($xpath->query('//*[@role="tab"]')->length)->toBe(2);
    expect($xpath->query('//*[@role="tabpanel"]')->length)->toBe(2);
    expect($xpath->query('//*[@role="tab"][@aria-selected="true"]')->length)->toBe(1);
    expect($xpath->query('//div[@role="tablist"][@hidden]')->length)->toBe(1);   // only the script reveals the tabs
    foreach (['PHP', 'Blade', 'v1.2.3', '60%', '345', '42', 'navi-crwn/HEL.ink', 'navi-crwn/LinkHop'] as $needle) {
        expect($html)->toContain($needle);
    }
    expect($xpath->query('//*[contains(@class,"ho-weeks")]/i')->length)->toBe(104);   // 52 weeks x 2 repos
    expect($xpath->query('//*[contains(@class,"ho-strip")]/i')->length)->toBe(30 * count(HopApps::all()));
    expect($html)->toContain('100%');   // the uptime of the one probe
    // three equal cards: privacy, GitHub, status
    expect($xpath->query('//a[contains(@class,"ho-card")]')->length)->toBe(3);
    expect($html)->toContain(route('privacy'))->toContain(route('status'))->toContain('https://github.com/navi-crwn?tab=repositories');
    // nothing is loaded from anywhere but this site; the script is deferred and has no inline code
    expect($page)->toMatch('#<script src="[^"]*/js/home-open\.js[^"]*" defer></script>#');
    expect($xpath->query('//script')->length)->toBe(0);   // the partial itself carries no script
    expect($html)->not->toMatch('#(src|href)="https?://(?!github\.com|localhost)#');
});

test('without any stored data the section shows a calm line, never zeros', function () {
    Http::swap(new Factory);
    Http::fake();
    $html = view('partials.home-open')->render();

    Http::assertNothingSent();
    expect($html)->toContain('data-ho-empty="github"')->toContain('data-ho-empty="uptime"')
        ->toContain('Repository numbers are not loaded yet')->toContain('The first numbers will show here soon');
    expect($html)->not->toContain('role="tab"')->not->toContain('ho-weeks')->not->toContain('ho-strip')->not->toContain('0%');
    expect($html)->toContain('https://github.com/navi-crwn/LinkHop');   // the links still work
    expect($html)->toContain(route('privacy'));
});

test('a repo without data yet says so while the others show their numbers', function () {
    fakeGithub('missing:LinkHop');
    $this->artisan('hop:github-stats')->assertSuccessful();
    $html = view('partials.home-open')->render();
    expect($html)->toContain('Numbers for this repository are not loaded yet.')->toContain('v1.2.3');
    expect(substr_count($html, 'role="tabpanel"'))->toBe(2);
});

test('the section is translated in every language', function (string $locale) {
    fakeGithub();
    $this->artisan('hop:github-stats')->assertSuccessful();
    config()->set('hop.apps.linkhop.url', 'https://link.example.com');
    ServiceCheck::create(['app_slug' => 'linkhop', 'kind' => 'health', 'ok' => true, 'checked_at' => now()->subHour()]);
    app()->setLocale($locale);
    $missing = [];
    app('translator')->handleMissingKeysUsing(function ($key) use (&$missing) {
        $missing[] = $key;

        return $key;
    });
    view('partials.home-open')->render();
    GithubStat::query()->delete();
    Cache::flush();
    ServiceCheck::query()->delete();
    view('partials.home-open')->render();

    expect($missing)->toBe([]);
})->with(['id', 'zh-CN', 'de', 'es', 'pt-BR', 'fr', 'ja', 'ru']);
