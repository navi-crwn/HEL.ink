<?php

use Hop\Safety\SafeBrowsing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
| The Safe Browsing lookup (v5 hashes:search): the address is never sent as it is, only base64 4-byte prefixes of
| the SHA-256 hashes of its expressions; one cache per provider and per answer; `unknown` when the provider cannot
| answer, because a failed lookup must never read as "safe"; and block/warn data is never trusted for longer than
| thirty minutes.
*/

beforeEach(function () {
    config([
        'safety.providers.safe_browsing_key' => 'test-key',
        'safety.providers.timeout' => 2,
        'safety.providers.threat_cache_minutes' => 30,
        'safety.providers.unknown_cache_minutes' => 5,
        'safety.providers.urlhaus_auth_key' => null,
        'safety.providers.spamhaus_dbl' => false,
    ]);
    Cache::flush();
    Http::preventStrayRequests();
});

function b64url(string $raw): string
{
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

/** A v5 answer: the full SHA-256 of one of the expressions that were asked about. */
function hashesHit(string $expression, string $threatType = 'SOCIAL_ENGINEERING', array $attributes = []): array
{
    return [
        'fullHashes' => [[
            'fullHash' => b64url(hash('sha256', $expression, true)),
            'fullHashDetails' => [['threatType' => $threatType, 'attributes' => $attributes]],
        ]],
        'cacheDuration' => '300s',
    ];
}

it('asks v5 hashes:search with base64 four-byte prefixes and never with the address itself', function () {
    Http::fake(['safebrowsing.googleapis.com/*' => Http::response(['fullHashes' => [], 'cacheDuration' => '300s'])]);

    $answer = app(SafeBrowsing::class)->check('https://mjdgdqgk.fnjphdp.com/wqamhcsom?r=1&t=2');

    expect($answer['status'])->toBe('clean')->and($answer['threats'])->toBe([]);

    Http::assertSent(function ($request) {
        expect($request->method())->toBe('GET')
            ->and($request->url())->toContain('safebrowsing.googleapis.com/v5/hashes:search')
            ->and($request->url())->toContain('key=test-key')
            ->and($request->url())->not->toContain('wqamhcsom')
            ->and($request->url())->not->toContain('fnjphdp');

        $prefixes = $request->data()['hashPrefixes'] ?? [];
        // The URL, the same without its query, the host, and the host with a slash: four expressions.
        expect($prefixes)->toBeArray()->toHaveCount(4);
        foreach ($prefixes as $prefix) {
            expect(strlen(base64_decode(strtr($prefix, '-_', '+/'), true) ?: ''))->toBe(4);
        }

        return true;
    });
});

it('takes a matching full hash as a block and keeps the threat types', function () {
    $url = 'https://example.com/phish';
    Http::fake(['*' => Http::response(hashesHit('https://example.com/phish', 'SOCIAL_ENGINEERING'))]);

    $answer = app(SafeBrowsing::class)->check($url);

    expect($answer['status'])->toBe('blocked')
        ->and($answer['threats'])->toContain('SOCIAL_ENGINEERING');
});

it('does not block on a full hash that does not belong to the address', function () {
    // The prefix matched by chance, but the full hash is of another address: the client must check it itself.
    Http::fake(['*' => Http::response(hashesHit('https://elsewhere.example/other', 'MALWARE'))]);

    expect(app(SafeBrowsing::class)->check('https://example.com/fine'))->toMatchArray(['status' => 'clean', 'threats' => []]);
});

it('ignores a detail the client does not understand or must not enforce', function (array $detail) {
    Http::fake(['*' => Http::response([
        'fullHashes' => [[
            'fullHash' => b64url(hash('sha256', 'https://example.com/x', true)),
            'fullHashDetails' => [$detail],
        ]],
        'cacheDuration' => '300s',
    ])]);

    expect(app(SafeBrowsing::class)->check('https://example.com/x')['status'])->toBe('clean');
})->with([
    'unknown threat type' => [['threatType' => 'SOME_FUTURE_THREAT', 'attributes' => []]],
    'unspecified attribute' => [['threatType' => 'MALWARE', 'attributes' => ['THREAT_ATTRIBUTE_UNSPECIFIED']]],
    'canary' => [['threatType' => 'SOCIAL_ENGINEERING', 'attributes' => ['CANARY']]],
]);

it('answers unknown, never clean, when the provider fails', function (int $status) {
    Http::fake(['*' => Http::response(['error' => ['code' => $status]], $status)]);

    expect(app(SafeBrowsing::class)->check('https://example.com/x'))->toMatchArray(['status' => 'unknown', 'threats' => []]);
})->with([429, 500, 503]);

it('answers unknown when the connection breaks or the key is missing', function (?string $key) {
    config(['safety.providers.safe_browsing_key' => $key]);
    if ($key !== null) {
        Http::fake(['*' => fn () => throw new RuntimeException('connection reset')]);
    }

    expect(app(SafeBrowsing::class)->check('https://example.com/y')['status'])->toBe('unknown');
    if ($key === null) {
        Http::assertNothingSent();
    }
})->with(['no key' => [null], 'broken connection' => ['test-key']]);

it('caches each answer apart and keeps a block no longer than thirty minutes', function () {
    $this->travelTo(now());

    Http::fakeSequence()
        ->push(hashesHit('https://example.com/one', 'MALWARE'))
        ->push(hashesHit('https://example.com/one', 'MALWARE'))
        ->push(['fullHashes' => [], 'cacheDuration' => '300s']);
    $sb = app(SafeBrowsing::class);
    $first = $sb->check('https://example.com/one');
    expect($first['status'])->toBe('blocked');
    expect($sb->check('https://example.com/one'))->toBe($first);
    Http::assertSentCount(1);

    // Another address is never served from that entry.
    $sb->check('https://example.com/two');
    Http::assertSentCount(2);

    // The provider said 300s, but block/warn data is not trusted beyond 30 minutes here.
    $this->travel(31)->minutes();
    expect($sb->check('https://example.com/one')['status'])->toBe('clean');
    Http::assertSentCount(3);
});

it('honours a shorter cacheDuration from the provider', function () {
    Http::fake(['*' => Http::response(['fullHashes' => [], 'cacheDuration' => '60s'])]);
    $sb = app(SafeBrowsing::class);

    $sb->check('https://example.com/short');
    Http::assertSentCount(1);

    $this->travel(61)->seconds();
    $sb->check('https://example.com/short');
    Http::assertSentCount(2);
});

it('asks again after a short while when the provider could not answer', function () {
    Http::fakeSequence()->push(['error' => []], 500)->push(['fullHashes' => [], 'cacheDuration' => '300s']);
    $sb = app(SafeBrowsing::class);

    expect($sb->check('https://example.com/flaky')['status'])->toBe('unknown');
    Http::assertSentCount(1);
    expect($sb->check('https://example.com/flaky')['status'])->toBe('unknown');
    Http::assertSentCount(1); // still the cached failure

    $this->travel(6)->minutes();
    expect($sb->check('https://example.com/flaky')['status'])->toBe('clean');
    Http::assertSentCount(2);
});

it('keeps its own cache namespace, so another provider cannot answer for it', function () {
    Http::fake(['*' => Http::response(['fullHashes' => [], 'cacheDuration' => '300s'])]);

    app(SafeBrowsing::class)->check('https://example.com/ns');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'safebrowsing.googleapis.com');
    });
    // Nothing was written under the old v4 single cache key.
    expect(Cache::get('abuse:remote:'.sha1('https://example.com/ns')))->toBeNull();
});
