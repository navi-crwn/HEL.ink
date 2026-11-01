<?php

use Hop\Ui\Assets;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the home page has one interactive section per public product in the manifest', function () {
    $html = $this->get('/')->assertOk()->getContent();

    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($dom);
    $apps = collect(Assets::family())->reject(fn ($app) => $app['slug'] === 'hub');
    expect($xpath->query('//*[@data-app-section]')->length)->toBe($apps->count());
    foreach ($apps as $app) {
        $slug = $app['slug'];
        $section = $xpath->query('//*[@data-app-section="'.$slug.'"]');
        expect($section->length)->toBe(1);
        expect($xpath->query('.//canvas[@data-hop-hero="'.$slug.'"]', $section->item(0))->length)->toBe(1);
        expect($xpath->query('.//*[@data-home-demo="'.$slug.'"]', $section->item(0))->length)->toBe(1);
        expect($xpath->query('.//ul[@class="h-feats"]/li', $section->item(0))->length)->toBe(3);
        expect($html)->toContain($app['url'])->toContain($app['icons']['mark']['url']);
    }
    expect(substr_count($html, 'aria-selected="true"'))->toBe(1);
});

test('the home page has the HOP sections and no back link', function () {
    $html = $this->get('/')->assertOk()->getContent();

    foreach (['id="apps"', 'id="connects"', 'id="open-title"', 'id="faq-title"'] as $section) {
        expect($html)->toContain($section);
    }
    expect($html)->not->toContain('Back to HOP');
    expect($html)->not->toContain('id="status"');
    expect(substr_count($html, '<section '))->toBe(8);
});

test('the home page is English by default and speaks eight languages', function () {
    $this->withHeader('Accept-Language', 'fr-FR')->get('/')->assertOk()->assertSee('One account. Every HOP app.');
    $this->get('/?lang=id')->assertOk()->assertSee('Satu akun. Semua aplikasi HOP.')->assertCookie('hop_lang', 'id', false);
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

test('home page copy never falls back to English', function (string $locale) {
    $missing = [];
    app('translator')->handleMissingKeysUsing(function ($key) use (&$missing) {
        $missing[] = $key;

        return $key;
    });
    $this->get('/?lang='.$locale)->assertOk();
    expect(array_values(array_unique($missing)))->toBe([]);
})->with(['en', 'id', 'zh', 'es', 'ar', 'pt', 'fr', 'ja']);

test('HOP pages and app pages are served with a back link', function (string $path, string $text) {
    $this->get($path)->assertOk()->assertSee($text)->assertSee('Back to HOP');
})->with([
    ['/how-it-works', 'ticket'],
    ['/security', 'security advisory'],
    ['/open-source', 'MIT license'],
    ['/signup', 'two-factor'],
    ['/about', 'About HOP'],
]);
