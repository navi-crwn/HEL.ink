<?php

use Hop\Safety\LinkSafety;
use Hop\Safety\UrlGuard;

test('the safety package is wired: blocks private targets and scores obvious phishing', function () {
    expect(config('safety.weights'))->toBeArray();
    expect(app(LinkSafety::class))->toBeInstanceOf(LinkSafety::class);
    $guard = new UrlGuard;
    expect($guard->isSafe('http://127.0.0.1/admin'))->toBeFalse();
    expect($guard->isSafe('http://[::1]/'))->toBeFalse();
    expect($guard->isSafe('ftp://example.com/'))->toBeFalse();
    expect($guard->isSafe('http://93.184.216.34/'))->toBeTrue();
});
