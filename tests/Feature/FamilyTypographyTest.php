<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('hub pages load the HOP family fonts from hel.ink, not a font CDN', function () {
    foreach (['/', '/login', '/about'] as $path) {
        $html = $this->get($path)->assertOk()->getContent();

        expect($html)->toContain('https://hel.ink/hop-assets/v6/fonts-amnesia.css')
            ->toContain('integrity="sha384-')
            ->not->toContain('fonts.bunny.net');
    }
});

test('the home page shows each app with its official mark and the interactive identity', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('https://hel.ink/hop-assets/v6/apps/biohop/mark.svg')
        ->toContain('https://hel.ink/hop-assets/v6/apps/linkhop/mark.svg')
        ->toContain('data-hop-hero="hub"')
        ->toContain('apps/hub/rabbit-mark.png');
});

test('the CSP allows the shared assets and drops the font CDN', function () {
    $csp = $this->get('/')->headers->get('Content-Security-Policy');

    expect($csp)->toMatch('/font-src [^;]*https:\/\/hel\.ink/')
        ->toMatch('/style-src [^;]*https:\/\/hel\.ink/')
        ->not->toContain('fonts.bunny.net');
});

test('the app stylesheets name no font the family dropped', function () {
    foreach (glob(resource_path('css/*.css')) as $file) {
        expect(file_get_contents($file))->not->toContain('Syne');
    }
});
