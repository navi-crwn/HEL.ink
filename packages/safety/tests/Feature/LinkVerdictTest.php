<?php

use Hop\Safety\LinkVerdict;
use Hop\Safety\UrlGuard;
use Hop\Safety\Verdict;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

// The verdict engine: one score for the destination, the chain it redirects through and who created the link,
// turned into allow / interstitial / block. Nothing here touches the network: DNS answers come from a table and
// HTTP from Http::fake().

const PUBLIC_IP = '93.184.216.34';

// Skip reason for tests that exercise the pinned fetch itself: without ext-curl the fetch is never attempted
// (fail closed), which the dedicated no-curl test asserts instead.
const NEEDS_CURL = 'Needs ext-curl: without it the pinned fetch is never attempted (it fails closed).';

/** Every host resolves to one public address unless the table says otherwise ([] means unresolvable). */
function verdictGuard(array $dns = []): UrlGuard
{
    return new class($dns) extends UrlGuard
    {
        public function __construct(private array $dns) {}

        protected function resolveHost(string $host): array
        {
            if (filter_var($host, FILTER_VALIDATE_IP)) {
                return [$host];
            }

            return $this->dns[$host] ?? [PUBLIC_IP];
        }
    };
}

function engine(array $dns = []): LinkVerdict
{
    app()->instance(UrlGuard::class, verdictGuard($dns));

    return app(LinkVerdict::class);
}

/**
 * Fake the web: the given addresses answer as listed, every other address is an ordinary HTML page. (Http::fake()
 * keeps the first matching stub, so the catch-all has to come last and cannot sit in beforeEach.)
 */
function fakeWeb(array $responses = []): void
{
    Http::fake($responses + ['*' => Http::response('<html>', 200, ['Content-Type' => 'text/html; charset=utf-8'])]);
}

beforeEach(function () {
    config(['safety.providers.spamhaus_dbl' => false]);
    Http::preventStrayRequests();
});

it('gives the expected verdict for every fixture', function (string $url, bool $guest, array $expected) {
    fakeWeb();
    $v = engine()->evaluate($url, guest: $guest);

    expect($v)->toBeInstanceOf(Verdict::class)
        ->and($v->verdict)->toBeIn($expected, implode(', ', $v->reasons));
})->with(function () {
    foreach (json_decode(file_get_contents(__DIR__.'/../fixtures/verdict-cases.json'), true) as $case) {
        yield $case['name'] => [$case['url'], $case['guest'], $case['verdict']];
    }
})->skip(! defined('CURLOPT_RESOLVE'), NEEDS_CURL);

it('returns a value object with the verdict, score, reasons, final url and hop count', function () {
    fakeWeb();
    $v = engine()->evaluate('https://github.com/navi-crwn');

    expect($v->verdict)->toBe(Verdict::ALLOW)
        ->and($v->score)->toBe(0)
        ->and($v->reasons)->toBe([])
        ->and($v->finalUrl)->toBe('https://github.com/navi-crwn')
        ->and($v->hops)->toBe(0)
        ->and($v->allows())->toBeTrue()
        ->and($v->blocks())->toBeFalse()
        ->and($v->needsInterstitial())->toBeFalse()
        ->and($v->toArray())->toBe(['verdict' => 'allow', 'score' => 0, 'reasons' => [], 'final_url' => 'https://github.com/navi-crwn', 'hops' => 0]);
})->skip(! defined('CURLOPT_RESOLVE'), NEEDS_CURL);

it('adds the guest penalty as a reason of its own', function () {
    fakeWeb();
    $v = engine()->evaluate('https://github.com/navi-crwn', guest: true);

    expect($v->score)->toBe(config('safety.weights.guest'))
        ->and(config('safety.weights.guest'))->toBe(25)
        ->and($v->reasons)->toBe(['guest'])
        ->and($v->verdict)->toBe(Verdict::ALLOW);
})->skip(! defined('CURLOPT_RESOLVE'), NEEDS_CURL);

it('turns the score into a verdict with the configured thresholds', function () {
    fakeWeb();
    expect(config('safety.verdict.thresholds'))->toBe(['block' => 70, 'interstitial' => 30]);
    $url = 'https://promo.example.xyz/';   // risky_tld: 30

    expect(engine()->evaluate($url)->verdict)->toBe(Verdict::INTERSTITIAL)
        ->and(engine()->evaluate($url, guest: true)->verdict)->toBe(Verdict::INTERSTITIAL);   // 55

    config(['safety.verdict.thresholds.block' => 50]);
    expect(engine()->evaluate($url, guest: true)->verdict)->toBe(Verdict::BLOCK);

    config(['safety.verdict.thresholds.interstitial' => 31]);
    expect(engine()->evaluate($url)->verdict)->toBe(Verdict::ALLOW);
})->skip(! defined('CURLOPT_RESOLVE'), NEEDS_CURL);

it('feeds every existing LinkSafety signal into the same score', function () {
    fakeWeb();
    $v = engine()->evaluate('https://mjdgdqgk.fnjphdp.com/wqamhcsom?r=AbCdEf123', guest: true);

    expect($v->reasons)->toContain('blacklisted', 'phishing', 'random_subdomain', 'guest')
        ->and($v->verdict)->toBe(Verdict::BLOCK)
        ->and($v->score)->toBeGreaterThanOrEqual(100);
});

it('follows redirects and reports the final address and the number of hops', function () {
    fakeWeb([
        'https://short.example/a' => Http::response('', 302, ['Location' => 'https://mid.example/b']),
        'https://mid.example/b' => Http::response('', 301, ['Location' => '/c?x=1']),
        'https://mid.example/c?x=1' => Http::response('', 307, ['Location' => '//final.example/landing']),
    ]);

    $v = engine()->evaluate('https://short.example/a');

    expect($v->finalUrl)->toBe('https://final.example/landing')
        ->and($v->hops)->toBe(3)
        ->and($v->reasons)->toContain('redirect_chain')
        ->and(config('safety.weights.redirect_chain'))->toBe(25)
        ->and($v->verdict)->toBe(Verdict::ALLOW);
})->skip(! defined('CURLOPT_RESOLVE'), NEEDS_CURL);

it('does not count one or two redirects against the link', function () {
    fakeWeb([
        'https://short.example/a' => Http::response('', 302, ['Location' => 'https://mid.example/b']),
        'https://mid.example/b' => Http::response('', 302, ['Location' => 'https://final.example/']),
    ]);

    $v = engine()->evaluate('https://short.example/a');

    expect($v->hops)->toBe(2)->and($v->reasons)->not->toContain('redirect_chain')->and($v->score)->toBe(0);
})->skip(! defined('CURLOPT_RESOLVE'), NEEDS_CURL);

it('judges the final address, not only the one that was submitted', function () {
    fakeWeb(['https://short.example/a' => Http::response('', 302, ['Location' => 'https://x1.ngrok-free.app/login'])]);

    $v = engine()->evaluate('https://short.example/a');

    expect($v->reasons)->toContain('tunnel_host')->and($v->verdict)->toBe(Verdict::BLOCK)
        ->and($v->finalUrl)->toBe('https://x1.ngrok-free.app/login');
})->skip(! defined('CURLOPT_RESOLVE'), NEEDS_CURL);

it('blocks when the final content is an Android package or a Windows executable', function (string $type) {
    fakeWeb(['https://cdn.example/download?id=42' => Http::response('', 200, ['Content-Type' => $type])]);

    $v = engine()->evaluate('https://cdn.example/download?id=42');

    expect($v->reasons)->toContain('executable_download')->and($v->verdict)->toBe(Verdict::BLOCK);
})->with([
    'apk' => ['application/vnd.android.package-archive'],
    'msdownload' => ['application/x-msdownload'],
    'msdos' => ['application/x-msdos-program; charset=binary'],
])->skip(! defined('CURLOPT_RESOLVE'), NEEDS_CURL);

it('asks with HEAD first and only falls back to GET when HEAD is refused', function () {
    Http::fake(function (Request $request) {
        if ($request->url() === 'https://strict.example/page' && $request->method() === 'HEAD') {
            return Http::response('', 405);
        }

        return Http::response('', 200, ['Content-Type' => 'text/html']);
    });

    engine()->evaluate('https://easy.example/page');
    engine()->evaluate('https://strict.example/page');

    $methods = Http::recorded()->map(fn ($pair) => $pair[0]->method().' '.$pair[0]->url())->all();
    expect($methods)->toBe(['HEAD https://easy.example/page', 'HEAD https://strict.example/page', 'GET https://strict.example/page']);
})->skip(! defined('CURLOPT_RESOLVE'), NEEDS_CURL);

it('fetches like a phone, with a short timeout, without the body and pinned to the checked addresses', function () {
    $seen = [];
    Http::fake(function (Request $request, array $options) use (&$seen) {
        $seen[] = [$request, $options];

        return Http::response('', 200);
    });

    engine()->evaluate('https://example.com/');

    if (defined('CURLOPT_RESOLVE')) {
        expect($seen)->toHaveCount(1);
        [$request, $options] = $seen[0];
        expect($request->header('User-Agent')[0])->toContain('Android', 'Mobile')
            ->and(config('safety.verdict.timeout'))->toBe(4)
            ->and($options['timeout'])->toBe(4)
            ->and($options['connect_timeout'])->toBe(4)
            ->and($options['allow_redirects'])->toBeFalse()
            ->and($options['stream'])->toBeTrue()
            ->and($options['curl'][CURLOPT_RESOLVE])->toBe(['example.com:443:'.PUBLIC_IP]);
    } else {
        // Without curl the fetch fails closed before any request is built, so the fake sees nothing.
        expect($seen)->toHaveCount(0);
    }
});

it('fails closed for live fetches when curl cannot pin the resolved address, in every environment', function (string $env) {
    app()->detectEnvironment(fn () => $env);
    // No fakes here on purpose: stray requests are allowed through the stub layer so that anything reaching
    // the transport layer is counted and refused by this middleware, which runs before the real handler and
    // never opens a socket. A count above zero means a fetch was attempted without DNS pinning.
    $attempts = 0;
    Http::preventStrayRequests(false); // let stray requests reach the middleware below, which counts and refuses them
    Http::globalMiddleware(function () use (&$attempts) {
        return function ($request, $options) use (&$attempts) {
            $attempts++;

            throw new RuntimeException('network attempted in test');
        };
    });

    $v = engine()->evaluate('https://example.com/', remote: false);

    Http::assertNothingSent();
    expect($attempts)->toBe(0, 'a fetch was attempted without DNS pinning')
        ->and($v->reasons)->toContain('unresolved_destination')
        ->and($v->verdict)->toBe(Verdict::INTERSTITIAL);
})->with(['testing environment' => ['testing'], 'production environment' => ['production']])
    ->skip(defined('CURLOPT_RESOLVE'), 'This checks the no-curl fallback.');

it('stops after five hops and treats the chain as unresolved', function () {
    $responses = [];
    for ($i = 0; $i < 8; $i++) {
        $responses["https://loop.example/$i"] = Http::response('', 302, ['Location' => 'https://loop.example/'.($i + 1)]);
    }
    fakeWeb($responses);

    $v = engine()->evaluate('https://loop.example/0');

    expect($v->hops)->toBe(5)
        ->and(config('safety.verdict.max_hops'))->toBe(5)
        ->and($v->reasons)->toContain('unresolved_destination', 'redirect_chain')
        ->and(Http::recorded())->toHaveCount(6);
})->skip(! defined('CURLOPT_RESOLVE'), NEEDS_CURL);

it('never fetches a hop that points at a private or local address', function () {
    fakeWeb(['https://short.example/meta' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/'])]);

    $v = engine(['short.example' => [PUBLIC_IP]])->evaluate('https://short.example/meta');

    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '169.254.169.254'));
    expect($v->reasons)->toContain('unresolved_destination')
        ->and($v->hops)->toBe(1)
        ->and($v->finalUrl)->toBe('http://169.254.169.254/latest/meta-data/');
})->skip(! defined('CURLOPT_RESOLVE'), NEEDS_CURL);

it('never fetches a hop whose host resolves to a private address', function () {
    fakeWeb(['https://short.example/in' => Http::response('', 302, ['Location' => 'https://intranet.example/'])]);

    $v = engine(['intranet.example' => ['10.0.0.5']])->evaluate('https://short.example/in');

    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'intranet.example'));
    expect($v->reasons)->toContain('unresolved_destination');
});

it('scores an unresolvable or unreachable destination without blocking on that alone', function (callable $setup, string $url) {
    $engine = $setup();

    $v = $engine->evaluate($url);

    expect($v->reasons)->toBe(['unresolved_destination'])
        ->and(config('safety.weights.unresolved_destination'))->toBe(50)
        ->and($v->verdict)->toBe(Verdict::INTERSTITIAL)
        ->and($v->finalUrl)->toBe($url)
        ->and($v->hops)->toBe(0);
})->with([
    'no dns answer' => [function () {
        fakeWeb();

        return engine(['nxdomain.example' => []]);
    }, 'https://nxdomain.example/'],
    'connection error' => [function () {
        Http::fake(fn () => throw new ConnectionException('timed out'));

        return engine();
    }, 'https://slow.example/'],
    'server error' => [function () {
        Http::fake(fn () => Http::response('', 503));

        return engine();
    }, 'https://down.example/'],
]);

it('blocks an address with a bare IP for a guest but not for a signed-in person', function () {
    fakeWeb();
    expect(engine()->evaluate('http://93.184.216.34/promo', guest: true)->verdict)->toBe(Verdict::BLOCK)
        ->and(engine()->evaluate('http://93.184.216.34/promo', guest: true)->reasons)->toContain('guest_ip_host')
        ->and(engine()->evaluate('http://93.184.216.34/promo')->verdict)->not->toBe(Verdict::BLOCK)
        ->and(engine()->evaluate('http://[2606:2800:220:1::1]/promo', guest: true)->verdict)->toBe(Verdict::BLOCK);
});

it('can judge without fetching, for a check at creation time or in a scan', function () {
    $v = engine()->evaluate('https://short.example/a', unroll: false);

    Http::assertNothingSent();
    expect($v->hops)->toBe(0)->and($v->finalUrl)->toBe('https://short.example/a')->and($v->verdict)->toBe(Verdict::ALLOW);
});

it('blocks an address it cannot read as a web link', function (?string $url) {
    $v = engine()->evaluate((string) $url);

    Http::assertNothingSent();
    expect($v->verdict)->toBe(Verdict::BLOCK)->and($v->reasons)->toContain('invalid_url');
})->with(['empty' => [''], 'no host' => ['https:///path'], 'no scheme' => ['example.com/page'], 'ftp' => ['ftp://example.com/'], 'javascript' => ['javascript:alert(1)']]);

it('takes the text around the link into account like assess() does', function () {
    fakeWeb();
    $v = engine()->evaluate('https://example.com/p', text: 'Two-Step Password');

    expect($v->reasons)->toContain('phishing')->and($v->verdict)->toBe(Verdict::BLOCK);
});

it('is one instance per request or job, like LinkSafety', function () {
    expect(app(LinkVerdict::class))->toBe(app(LinkVerdict::class));
});

it('lists the reasons once each, as translatable keys', function () {
    fakeWeb([
        'https://a.example/' => Http::response('', 302, ['Location' => 'https://files.example.com/x.apk']),
        'https://files.example.com/x.apk' => Http::response('', 200, ['Content-Type' => 'application/vnd.android.package-archive']),
    ]);

    $v = engine()->evaluate('https://a.example/', guest: true);

    expect($v->reasons)->toBe(array_values(array_unique($v->reasons)))
        ->and($v->reasons)->each->toMatch('/^[a-z_]+$/');
});
