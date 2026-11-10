<?php

use Hop\Ui\Apps;
use Hop\Ui\Assets;
use Illuminate\Support\Facades\Blade;

test('the bundled manifest lists the public HOP apps with their marks', function () {
    $family = Assets::family('linkhop');
    $slugs = array_column($family, 'slug');

    expect($slugs)->toContain('hub', 'linkhop', 'biohop', 'pixelhop', 'notehop', 'filehop', 'payhop')->not->toContain('imperium');
    expect(collect($family)->firstWhere('slug', 'linkhop')['current'])->toBeTrue();
    foreach ($family as $app) {
        expect($app['icons']['mark']['url'])->toStartWith('https://hel.ink/hop-assets/')
            ->and($app['icons']['mark']['integrity'])->toStartWith('sha384-');
    }
});

test('fonts and floating tiles load from hel.ink with SRI', function () {
    $html = Blade::render('<x-hop::fonts /><x-hop::float mode="section" />');

    expect($html)->toContain('https://hel.ink/hop-assets/v9/fonts-amnesia.css')
        ->toContain('https://hel.ink/hop-assets/v9/hop-float.js')
        ->toContain('integrity="sha384-')
        ->toContain('crossorigin="anonymous"')
        ->toContain('data-hop-float="section"');
});

test('the dialogs and reminder cards load from hel.ink with SRI', function () {
    $html = Blade::render('<x-hop::ui />');

    expect($html)->toContain('https://hel.ink/hop-assets/v9/hop-ui.css')
        ->toContain('https://hel.ink/hop-assets/v9/hop-ui.js')
        ->toContain('integrity="sha384-')
        ->toContain('crossorigin="anonymous"');
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

test('the uniform footer component renders 4 columns including the HOP family', function () {
    $html = Blade::render('<x-hop::footer app="linkhop" name="LinkHop" />');

    expect($html)->toContain('LinkHop')
        ->toContain('Support')
        ->toContain('Policies')
        ->toContain('HOP Family')
        ->not->toContain('hreflang=')
        ->toContain('Hop Easy Link')
        ->toContain('href="https://hel.ink"')
        ->toContain('href="https://note.hel.ink"')
        ->toContain('href="https://bio.hel.ink"')
        ->toContain('href="https://pixel.hel.ink"')
        ->toContain('aria-current="page"')
        ->toContain('Open source under MIT License');
});

test('the family list is exactly the public apps of the bundled manifest', function () {
    $manifest = json_decode(file_get_contents(dirname(__DIR__, 2).'/resources/hop-assets/apps.json'), true);
    $public = array_values(array_map(fn ($a) => $a['slug'], array_filter($manifest['apps'], fn ($a) => $a['public'] ?? false)));

    expect($manifest['version'])->toBe('v9')
        ->and(array_column(Assets::family(), 'slug'))->toBe($public);
});

test('PayHop has its own menu icon', function () {
    expect(Apps::ICONS)->toHaveKey('pay');
    expect(Apps::icon('pay'))->not->toBe(Apps::icon('file'))->not->toBe(Apps::icon('globe'));
});

test('FileHop has its own menu icon', function () {
    expect(Apps::ICONS)->toHaveKey('file');
    expect(Apps::icon('file'))->not->toBe(Apps::icon('note'))->not->toBe(Apps::icon('globe'));
});

test('the footer shows a brand slot instead of the family row when one is given', function () {
    $html = Blade::render('<x-hop::footer app="hub" name="HEL.ink"><x-slot:brand><span>Custom brand</span></x-slot:brand></x-hop::footer>');

    expect($html)->toContain('hop-footer-brand')->toContain('Custom brand')->not->toContain('Part of');
});
