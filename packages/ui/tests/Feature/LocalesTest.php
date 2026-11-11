<?php

use Hop\Ui\Locales;
use Illuminate\Support\Facades\Blade;

test('the family ships exactly nine languages and Arabic is not one of them', function () {
    expect(Locales::codes())->toBe(['en', 'zh-CN', 'de', 'id', 'ja', 'pt-BR', 'es', 'fr', 'ru'])
        ->and(Locales::codes())->toHaveCount(9)->not->toContain('ar');
});

test('a stored language is mapped to the new set and an unknown or retired one falls back to English', function () {
    expect(Locales::normalize('zh'))->toBe('zh-CN')
        ->and(Locales::normalize('pt'))->toBe('pt-BR')
        ->and(Locales::normalize('PT-br'))->toBe('pt-BR')
        ->and(Locales::normalize('de'))->toBe('de')
        ->and(Locales::normalize('ar'))->toBe('en')
        ->and(Locales::normalize('ko'))->toBe('en')
        ->and(Locales::normalize(null))->toBe('en')
        ->and(Locales::resolve('ar'))->toBeNull();
});

test('the language menu lists nine entries, native name left and short code right', function () {
    app()->setLocale('de');
    $html = Blade::render('<x-hop::language-switcher />');

    expect(substr_count($html, 'hreflang='))->toBe(9)
        ->and($html)->toContain('<span>Deutsch</span><small>DE</small>')
        ->toContain('<span>简体中文</span><small>中文</small>')
        ->toContain('<span>Português (Brasil)</span><small>PT</small>')
        ->toContain('<span>日本語</span><small>日本語</small>')
        ->toContain('<span>Русский</span><small>RU</small>')
        ->toContain('hreflang="zh-CN"')->toContain('hreflang="pt-BR"')
        ->not->toContain('العربية')->not->toContain('hreflang="ar"')
        ->and($html)->toMatch('/hreflang="de"[^>]*aria-current/');
});

test('a retired stored language shows English as the current one', function () {
    app()->setLocale('ar');
    $html = Blade::render('<x-hop::language-switcher />');
    expect($html)->toMatch('/hreflang="en"[^>]*aria-current/')->toContain('>EN</span>');
});

test('the footer has no language row, the top bar menu is the one place to switch', function () {
    app()->setLocale('en');
    $html = Blade::render('<x-hop::footer app="biohop" name="BioHop" />');
    expect($html)->not->toContain('hreflang=')->not->toContain('?lang=')->toContain('Open source under MIT License');
});
