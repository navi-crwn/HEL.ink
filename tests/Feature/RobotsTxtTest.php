<?php

test('every Sitemap line in robots.txt points at a URL the hub serves', function () {
    $robots = file_get_contents(public_path('robots.txt'));
    expect($robots)->toContain('User-agent');
    preg_match_all('/^Sitemap:\s*(\S+)/mi', $robots, $m);

    foreach ($m[1] as $url) {
        $this->get(parse_url($url, PHP_URL_PATH))->assertOk();
    }
});

test('bio pages stay crawlable on hel.ink but their click links are not', function () {
    $robots = file_get_contents(public_path('robots.txt'));
    expect($robots)->toContain('Allow: /b/
')->and($robots)->toContain('Disallow: /b/click/
');
    // Google follows the longest matching rule, so /b/click/ (11 characters) beats /b/ (3).
    expect(strlen('/b/click/'))->toBeGreaterThan(strlen('/b/'));
});
