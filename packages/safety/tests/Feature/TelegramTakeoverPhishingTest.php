<?php

use Hop\Safety\LinkSafety;
use Hop\Safety\Models\DomainBlacklist;
use Illuminate\Support\Facades\Schema;

// A reconstruction of the page a short link led to on 2026-11-05: a Telegram account-takeover form that threat feeds
// had not listed, so only the package's own signals can catch it. The random query values are made up.
const TAKEOVER_URL = 'https://mjdgdqgk.fnjphdp.com/wqamhcsom?r=AbCdEf123&t=9988776655';

// Keep the DNS lookup (Spamhaus) off so nothing here touches the network.
beforeEach(fn () => config(['safety.providers.spamhaus_dbl' => false]));

it('flags the live takeover address with every signal and refuses it', function () {
    $safety = app(LinkSafety::class);

    $r = $safety->assess(TAKEOVER_URL);

    expect($r['signals'])->toContain('blacklisted', 'phishing', 'random_subdomain')
        ->and($r['score'])->toBeGreaterThanOrEqual(config('safety.thresholds.suspend_user'))
        ->and($safety->rejectionReason(TAKEOVER_URL))->toBe('This destination is not allowed due to our security policy.');
});

it('scores the page title and text of the takeover form as phishing', function (string $text) {
    $r = app(LinkSafety::class)->assess('https://example.com/p', $text);

    expect($r['signals'])->toContain('phishing');
})->with([
    'title' => ['Account Security Compliance Check'],
    'form' => ['Enter the verification code and two-step password'],
    'password field' => ['Two-Step Password'],
    'confirm button' => ['Yes, it was me'],
    'promise' => ['restore your account status'],
    'underscores' => ['account_security_compliance'],
]);

it('scores the same phrases in a URL path or query, encoded the way a browser sends them', function (string $url) {
    $r = app(LinkSafety::class)->assess($url);

    expect($r['signals'])->toContain('phishing');
})->with([
    'path with dashes' => ['https://example.com/account-security-compliance'],
    'path percent-encoded' => ['https://example.com/two%2Dstep%20password'],
    'query with plus' => ['https://example.com/?title=yes,+it+was+me'],
    'query percent-encoded' => ['https://example.com/?m=restore%20your%20account%20status'],
]);

it('does not call ordinary text or addresses phishing', function (string $url, string $text) {
    $r = app(LinkSafety::class)->assess($url, $text);

    expect($r['signals'])->not->toContain('phishing');
})->with([
    'profile' => ['https://github.com/navi-crwn', 'navi-crwn on GitHub'],
    'security docs' => ['https://example.com/docs/security', 'How to turn on two-step verification'],
    'compliance' => ['https://example.com/compliance', 'Compliance check for our yearly audit'],
    'it was me' => ['https://example.com/blog', 'Yes, it was Mexico we visited last year'],
]);

it('blocks the takeover domain and its subdomains from the bundled blacklist without a database table', function () {
    expect(Schema::hasTable('domain_blacklist'))->toBeFalse();
    $safety = app(LinkSafety::class);

    expect($safety->blacklistCategory('fnjphdp.com'))->toBe('phishing')
        ->and($safety->blacklistCategory('mjdgdqgk.fnjphdp.com'))->toBe('phishing')
        ->and($safety->blacklistCategory('a.b.fnjphdp.com'))->toBe('phishing')
        ->and($safety->blacklistCategory('notfnjphdp.com'))->toBeFalse()
        ->and($safety->blacklistCategory('fnjphdp.com.example.org'))->toBeFalse();
});

it('keeps reading the shared domain_blacklist table next to the bundled list', function () {
    Schema::create('domain_blacklist', function ($table) {
        $table->id();
        $table->string('domain');
        $table->string('match_type')->default('exact');
        $table->string('category')->nullable();
        $table->text('notes')->nullable();
        $table->timestamps();
    });
    DomainBlacklist::create(['domain' => 'casino-example.test', 'match_type' => 'exact', 'category' => 'gambling']);
    $safety = app(LinkSafety::class);

    expect($safety->blacklistCategory('casino-example.test'))->toBe('gambling')
        ->and($safety->blacklistCategory('fnjphdp.com'))->toBe('phishing');
});

it('raises the score for a random-looking subdomain on an unknown domain without blocking on its own', function (string $url) {
    $safety = app(LinkSafety::class);

    $r = $safety->assess($url);

    expect($r['signals'])->toContain('random_subdomain')
        ->and($r['score'])->toBeLessThan(config('safety.thresholds.disable'))
        ->and(config('safety.block_categories'))->not->toContain('random_subdomain')
        ->and($safety->rejectionReason($url))->toBeNull();
})->with([
    'no vowels' => ['https://mjdgdqgk.kxqwrtp-example.com/'],
    'consonant heavy' => ['https://xkpqtrvza.some-new-site.net/login'],
    'hex id' => ['https://f3b9c2d7e1.another-site.org/'],
    'deeper label' => ['https://cdn.zxcvbqwrt.another-site.org/'],
    'country code suffix' => ['https://qwrtzpsk.example.co.id/'],
]);

it('does not treat ordinary subdomains, known services or CDN hosts as random', function (string $url) {
    $r = app(LinkSafety::class)->assess($url);

    expect($r['signals'])->not->toContain('random_subdomain')->and($r['score'])->toBe(0);
})->with([
    'no subdomain' => ['https://github.com/navi-crwn'],
    'word label' => ['https://docs.github.com/en'],
    'short label' => ['https://cdn.jsdelivr.net/npm/vue@3/dist/vue.global.js'],
    'digits only' => ['https://2024.example.com/'],
    'readable words' => ['https://static-assets.example.com/app.css'],
    'cloudfront' => ['https://d1a2b3c4d5e6f7.cloudfront.net/lib.js'],
    'amazon s3' => ['https://s3-xkpqtrvzb.s3.amazonaws.com/file'],
    'google user content' => ['https://lh3xkpqtrvz.googleusercontent.com/a.png'],
    'github user content' => ['https://xkpqtrvzab.githubusercontent.com/f'],
    'own family' => ['https://xkpqtrvzab.hel.ink/'],
]);
