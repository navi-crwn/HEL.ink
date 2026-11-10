<?php

use Hop\Safety\LinkSafety;
use Illuminate\Support\Facades\Http;

// Fake key only.
const LOOKUP_KEY = 'FAKEKEY0notehop0audit0000000000000000000';

/** A v5 answer: the full hash of one expression that was asked about. */
function fragmentAnswer(string $expression, string $threatType): array
{
    return [
        'fullHashes' => [[
            'fullHash' => rtrim(strtr(base64_encode(hash('sha256', $expression, true)), '+/', '-_'), '='),
            'fullHashDetails' => [['threatType' => $threatType, 'attributes' => []]],
        ]],
        'cacheDuration' => '300s',
    ];
}

// A fragment never reaches the server a browser connects to, so it must not reach a third party either. Since the
// move to v5 not even the address leaves: only four-byte hash prefixes do.
it('never sends a fragment, or the address itself, to Safe Browsing', function (string $url) {
    config(['safety.providers.safe_browsing_key' => 'FAKE-API-KEY', 'safety.providers.spamhaus_dbl' => false]);
    Http::fake(['*' => Http::response(['fullHashes' => [], 'cacheDuration' => '300s'], 200)]);

    app(LinkSafety::class)->assess($url, '', remote: true);

    Http::assertSentCount(1);
    Http::assertSent(function ($request) {
        $sent = $request->url().json_encode($request->data());

        return str_contains($request->url(), 'safebrowsing')
            && ! str_contains($sent, LOOKUP_KEY)
            && ! str_contains($sent, '#')
            && ! str_contains($sent, 'example.com')      // not even the host: v5 sends hash prefixes
            && ! str_contains($sent, 'page');
    });
})->with([
    'k' => ['https://example.com/page#k='.LOOKUP_KEY.'&x=1'],
    'a question mark before k' => ['https://example.com/page#?k='.LOOKUP_KEY],
    'owner token' => ['https://example.com/page#a='.LOOKUP_KEY],
    'an ordinary fragment' => ['https://example.com/page#section-2'],
    'a bare #' => ['https://example.com/page#'],
]);

it('does not send a fragment to Safe Browsing for a NoteHop address either', function () {
    config(['safety.providers.safe_browsing_key' => 'FAKE-API-KEY', 'safety.providers.spamhaus_dbl' => false]);
    Http::fake(['*' => Http::response(['fullHashes' => [], 'cacheDuration' => '300s'], 200)]);

    app(LinkSafety::class)->assess('https://note.hel.ink/Ab3dEf#k='.LOOKUP_KEY, '', remote: true);

    Http::assertSent(fn ($request) => ! str_contains($request->url().json_encode($request->data()), LOOKUP_KEY));
});

it('keeps the lookup working: the address without a fragment still yields a Safe Browsing verdict', function () {
    config(['safety.providers.safe_browsing_key' => 'FAKE-API-KEY', 'safety.providers.spamhaus_dbl' => false]);
    Http::fake(['*' => Http::response(fragmentAnswer('https://example.com/bad?x=1', 'MALWARE'), 200)]);

    $r = app(LinkSafety::class)->assess('https://example.com/bad?x=1#frag', '', remote: true);

    expect($r['signals'])->toContain('malware');
});

it('does not put the fragment in the cache key of the lookup either', function () {
    config(['safety.providers.safe_browsing_key' => 'FAKE-API-KEY', 'safety.providers.spamhaus_dbl' => false]);
    Http::fake(['*' => Http::response(['fullHashes' => [], 'cacheDuration' => '300s'], 200)]);
    $safety = app(LinkSafety::class);

    $safety->assess('https://example.com/page#k='.LOOKUP_KEY, '', remote: true);
    $safety->assess('https://example.com/page#k=ANOTHERFAKEKEY', '', remote: true);

    // the second call is answered from the cache: both addresses are the same address to a server
    Http::assertSentCount(1);
});
