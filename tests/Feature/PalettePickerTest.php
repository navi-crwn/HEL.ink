<?php

use App\Models\User;

/** Family colour sets (contract L-10): the picker in the dashboard sidebar, nothing on the public pages. */
function paletteDashboard(string $q = ''): string
{
    return test()->withoutVite()->actingAs(User::factory()->create())->get('/dashboard'.$q)->assertOk()->getContent();
}

test('the dashboard sidebar has the picker above the foot, with the three sets in order', function () {
    $html = paletteDashboard();
    preg_match_all('#data-palette-choice="([a-z]+)"#', $html, $m);
    expect($m[1])->toBe(['classic', 'mist', 'sand']);
    expect(strpos($html, 'data-palette-picker'))->toBeLessThan(strpos($html, '<ul class="grid gap-0.5'));
    expect($html)->toContain("hopSetPalette('mist')");
});

test('the page boot script knows the colour set, on the dashboard and on the public pages alike', function () {
    expect(paletteDashboard())->toContain('hop_palette=');
    // The public home page has no picker (this stage is the dashboard only) and its colours do not change.
    $home = test()->withoutVite()->get('/')->assertOk()->getContent();
    expect($home)->not->toContain('data-palette-choice');
});

test('the labels are in all nine languages', function () {
    foreach (['en', 'zh-CN', 'de', 'id', 'ja', 'pt-BR', 'es', 'fr', 'ru'] as $locale) {
        $map = json_decode(file_get_contents(lang_path($locale.'.json')), true);
        foreach (['Colours', 'Classic', 'Mist', 'Sand'] as $key) {
            expect($map[$key] ?? '')->not->toBe('');
        }
    }
    expect(paletteDashboard('?lang=id'))->toContain('Warna')->toContain('Kabut')->toContain('Pasir');
});

test('the stylesheet only acts under the attribute: classic has no rule at all', function () {
    $css = file_get_contents(resource_path('css/palette.css'));
    $css = preg_replace('#/\*.*?\*/#s', '', $css);
    foreach (explode('}', $css) as $block) {
        if (! str_contains($block, '{')) {
            continue;
        }
        $selector = trim(strstr($block, '{', true));
        if ($selector === '' || str_starts_with($selector, '.pal-')) {
            continue; // the picker itself
        }
        expect($selector)->toContain('data-palette');
    }
    expect($css)->not->toContain('hop_palette_depth')->not->toContain('data-palette-depth');
});
