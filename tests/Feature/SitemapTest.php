<?php

test('sitemap.xml answers 200 with the marketing pages and the apps', function () {
    $res = $this->get('/sitemap.xml')->assertOk();
    expect($res->headers->get('Content-Type'))->toContain('application/xml');
    $body = $res->getContent();
    expect($body)->toContain('<urlset')->toContain('/about</loc>')->toContain('/changelog</loc>');
    expect(simplexml_load_string($body))->not->toBeFalse();
});
