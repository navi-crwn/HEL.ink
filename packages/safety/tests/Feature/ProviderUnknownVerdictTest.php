<?php

use Hop\Safety\LinkSafety;
use Hop\Safety\LinkVerdict;
use Hop\Safety\Verdict;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

// A provider that cannot answer must never read as "safe": the link is unverified, which is a warning, not a pass.
it('adds unverified_destination when Safe Browsing could not answer', function () {
    config(['safety.providers.safe_browsing_key' => 'FAKE', 'safety.providers.spamhaus_dbl' => false, 'safety.providers.urlhaus_auth_key' => null]);
    Cache::flush();
    Http::fake(['*' => Http::response(['error' => []], 503)]);

    $status = app(LinkSafety::class)->providerStatus('https://example.com/x');

    expect($status['safe_browsing'])->toBe('unknown');
});

it('keeps clean and blocked apart from unknown', function (array $answer, string $expected) {
    config(['safety.providers.safe_browsing_key' => 'FAKE', 'safety.providers.spamhaus_dbl' => false, 'safety.providers.urlhaus_auth_key' => null]);
    Cache::flush();
    Http::fake(['*' => Http::response($answer, 200)]);

    expect(app(LinkSafety::class)->providerStatus('https://example.com/x')['safe_browsing'])->toBe($expected);
})->with([
    'nothing found' => [['fullHashes' => [], 'cacheDuration' => '300s'], 'clean'],
    'a match' => [[
        'fullHashes' => [[
            'fullHash' => rtrim(strtr(base64_encode(hash('sha256', 'https://example.com/x', true)), '+/', '-_'), '='),
            'fullHashDetails' => [['threatType' => 'MALWARE', 'attributes' => []]],
        ]],
        'cacheDuration' => '300s',
    ], 'blocked'],
]);

it('turns an unanswered provider into an unverified warning, not an allow', function () {
    config(['safety.providers.safe_browsing_key' => 'FAKE', 'safety.providers.spamhaus_dbl' => false, 'safety.providers.urlhaus_auth_key' => null]);
    Cache::flush();
    Http::fake(['*' => Http::response(['error' => []], 500)]);

    /** @var LinkVerdict $verdict */
    $verdict = app(LinkVerdict::class);
    $result = $verdict->evaluate('https://example.com/plain', guest: false, unroll: false, remote: true);

    expect($result->reasons)->toContain('unverified_destination')
        ->and($result->verdict)->not->toBe(Verdict::ALLOW);
});
