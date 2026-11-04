<?php

test('every Sitemap line in robots.txt points at a URL the hub serves', function () {
    $robots = file_get_contents(public_path('robots.txt'));
    expect($robots)->toContain('User-agent');
    preg_match_all('/^Sitemap:\s*(\S+)/mi', $robots, $m);

    foreach ($m[1] as $url) {
        $this->get(parse_url($url, PHP_URL_PATH))->assertOk();
    }
});
