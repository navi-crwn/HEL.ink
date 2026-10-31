<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the home page has an accessible tab per app', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('role="tablist"')->toContain('data-family-tabs');
    foreach (['linkhop', 'biohop', 'pixelhop', 'notehop'] as $app) {
        expect($html)->toContain('id="tab-'.$app.'"')->toContain('aria-controls="panel-'.$app.'"')->toContain('id="panel-'.$app.'"');
    }
    expect(substr_count($html, 'aria-selected="true"'))->toBe(1);
});

test('the home page has the HOP sections and no back link', function () {
    $html = $this->get('/')->assertOk()->getContent();

    foreach (['id="how"', 'id="privacy"', 'id="open"', 'id="status"', 'id="faq"', 'id="contact"'] as $section) {
        expect($html)->toContain($section);
    }
    expect($html)->not->toContain('Back to HOP');
});

test('the home page is English by default and speaks eight languages', function () {
    $this->withHeader('Accept-Language', 'fr-FR')->get('/')->assertOk()->assertSee('One hop to every app.');
    $this->get('/?lang=id')->assertOk()->assertSee('Satu lompatan ke semua aplikasi.')->assertCookie('hop_lang', 'id', false);
    $this->get('/?lang=ar')->assertOk()->assertSee('dir="rtl"', false);
    $this->get('/')->assertSee('hreflang="ja"', false);
});

test('every language file has every string, with placeholders kept', function () {
    $base = json_decode(file_get_contents(lang_path('id.json')), true);
    foreach (['zh', 'es', 'ar', 'pt', 'fr', 'ja'] as $locale) {
        $other = json_decode(file_get_contents(lang_path($locale.'.json')), true);
        expect(array_keys($other))->toEqual(array_keys($base));
        expect($other['More about :app'])->toContain(':app');
    }
});

test('HOP pages and app pages are served with a back link', function (string $path, string $text) {
    $this->get($path)->assertOk()->assertSee($text)->assertSee('Back to HOP');
})->with([
    ['/how-it-works', 'ticket'],
    ['/security', 'security advisory'],
    ['/open-source', 'MIT license'],
    ['/signup', 'two-factor'],
    ['/about', 'About HOP'],
]);
