<?php

/*
 * The HOP family asset manifest (public/hop-assets/apps.json) is the single source
 * for logos, fonts, floating tiles and the app list used by every HOP app.
 */

function hopAssets(): array
{
    return json_decode(file_get_contents(public_path('hop-assets/apps.json')), true, flags: JSON_THROW_ON_ERROR);
}

function hopAssetPath(string $url): string
{
    $prefix = 'https://hel.ink/hop-assets/';
    expect($url)->toStartWith($prefix);

    return public_path('hop-assets/'.substr($url, strlen($prefix)));
}

test('every asset in the manifest exists and matches its SRI hash', function () {
    $manifest = hopAssets();
    $entries = array_values($manifest['assets']);
    foreach ($manifest['apps'] as $app) {
        array_push($entries, ...array_values($app['icons']));
    }

    expect($entries)->not->toBeEmpty();
    foreach ($entries as $entry) {
        $path = hopAssetPath($entry['url']);
        expect(is_file($path))->toBeTrue("missing {$entry['url']}");
        expect($entry['integrity'])->toBe('sha384-'.base64_encode(hash_file('sha384', $path, true)));
    }
});

test('the manifest lists the public HOP apps at the addresses the hub uses', function () {
    $apps = collect(hopAssets()['apps'])->keyBy('slug');

    expect($apps->keys()->all())->toContain('hub', 'linkhop', 'biohop', 'pixelhop', 'notehop');
    foreach (['linkhop', 'biohop', 'pixelhop', 'notehop'] as $slug) {
        expect($apps[$slug]['url'])->toBe(rtrim((string) config("hop.apps.{$slug}.url"), '/'));
        expect($apps[$slug]['public'])->toBeTrue();
    }
    expect($apps['imperium']['public'])->toBeFalse();
});

test('BioHop uses its official purple document mark everywhere', function () {
    $mark = file_get_contents(hopAssetPath(collect(hopAssets()['apps'])->firstWhere('slug', 'biohop')['icons']['mark']['url']));

    expect($mark)->toContain('#d946ef')->toContain('#8b5cf6');
    expect(file_get_contents(public_path('hop-assets/v1/hop-float.js')))->not->toContain('leaf');
});

test('fonts.css only points at fonts that ship with it', function () {
    $css = file_get_contents(public_path('hop-assets/v1/fonts.css'));
    preg_match_all('/url\(([^)]+)\)/', $css, $m);

    expect($m[1])->not->toBeEmpty();
    foreach ($m[1] as $rel) {
        expect(is_file(public_path('hop-assets/v1/'.$rel)))->toBeTrue("missing font {$rel}");
    }
    expect($css)->toContain("font-family: 'Syne'")
        ->toContain("font-family: 'Plus Jakarta Sans'")
        ->toContain("font-family: 'JetBrains Mono'")
        ->toContain('--hop-font-body');
});
