<?php

use Hop\Ui\Assets;
use Illuminate\Support\Facades\Blade;

test('the bundled manifest lists the public HOP apps with their marks', function () {
    $family = Assets::family('linkhop');
    $slugs = array_column($family, 'slug');

    expect($slugs)->toContain('hub', 'linkhop', 'biohop', 'pixelhop', 'notehop')->not->toContain('imperium');
    expect(collect($family)->firstWhere('slug', 'linkhop')['current'])->toBeTrue();
    foreach ($family as $app) {
        expect($app['icons']['mark']['url'])->toStartWith('https://hel.ink/hop-assets/')
            ->and($app['icons']['mark']['integrity'])->toStartWith('sha384-');
    }
});

test('fonts and floating tiles load from hel.ink with SRI', function () {
    $html = Blade::render('<x-hop::fonts /><x-hop::float mode="section" />');

    expect($html)->toContain('https://hel.ink/hop-assets/v1/fonts.css')
        ->toContain('https://hel.ink/hop-assets/v1/hop-float.js')
        ->toContain('integrity="sha384-')
        ->toContain('crossorigin="anonymous"')
        ->toContain('data-hop-float="section"');
});

test('the family section links every app except the current one', function () {
    $html = Blade::render('<x-hop::family current="biohop" />');

    expect($html)->toContain('href="https://link.hel.ink"')
        ->toContain('href="https://note.hel.ink"')
        ->not->toContain('href="https://bio.hel.ink"')
        ->toContain('aria-current="page"');
});

test('the back link defaults to this app home', function () {
    expect(Blade::render('<x-hop::back label="Back to LinkHop" />'))->toContain('href="'.url('/').'"')->toContain('Back to LinkHop');
});
