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

    expect($apps->keys()->all())->toContain('hub', 'linkhop', 'biohop', 'pixelhop', 'notehop', 'filehop', 'payhop');
    foreach (['linkhop', 'biohop', 'pixelhop', 'notehop', 'filehop', 'payhop'] as $slug) {
        expect($apps[$slug]['url'])->toBe(rtrim((string) config("hop.apps.{$slug}.url"), '/'));
        expect($apps[$slug]['public'])->toBeTrue();
    }
    expect($apps['imperium']['public'])->toBeFalse();
});

test('PayHop has its mark, PWA icons, name font and home art in v9, and the 3D map is the mark', function () {
    $app = collect(hopAssets()['apps'])->firstWhere('slug', 'payhop');
    expect($app['url'])->toBe('https://pay.hel.ink')->and($app['color'])->toBe('#14b8a6');
    foreach (['mark', 'favicon', 'icon_192', 'icon_512', 'apple_touch'] as $kind) {
        expect(is_file(hopAssetPath($app['icons'][$kind]['url'])))->toBeTrue("missing {$kind}");
    }
    foreach (['light', 'dark'] as $look) {
        expect(is_file(public_path("hop-assets/v9/home/payhop-{$look}.webp")))->toBeTrue()
            ->and(filesize(public_path("hop-assets/v9/home/payhop-{$look}.webp")))->toBeLessThan(60 * 1024);
    }
    expect(is_file(public_path('hop-assets/v9/fonts/brand/payhop-name.woff2')))->toBeTrue()
        ->and(is_file(public_path('hop-assets/v9/fonts/LICENSE-unbounded.txt')))->toBeTrue()
        ->and(file_get_contents(public_path('hop-assets/v9/hop-brand.css')))->toContain('.hop-name-payhop');

    // The block map in hop-hero3d.js is the mark's 8x8 grid: '#' coloured cell, 'w' white cell, '.' empty.
    $svg = file_get_contents(hopAssetPath($app['icons']['mark']['url']));
    preg_match_all('/<rect x="(\d+)" y="(\d+)" width="4" height="4"( fill="#fff")?\/>/', $svg, $cells, PREG_SET_ORDER);
    $grid = array_fill(0, 8, str_repeat('.', 8));
    foreach ($cells as $c) {
        $grid[intdiv((int) $c[2], 4)][intdiv((int) $c[1], 4)] = isset($c[3]) ? 'w' : '#';
    }
    $js = file_get_contents(public_path('hop-assets/v9/hop-hero3d.js'));
    preg_match("/payhop: \[([^\]]+)\]/", $js, $map);
    expect(str_replace(["'", ' '], '', $map[1]))->toBe(implode(',', $grid));
});

test('hop-assets v9 only adds to v8: every v8 file is in v9 and unchanged unless PayHop needed it, or v9 names itself', function () {
    $changed = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(public_path('hop-assets/v8'), FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        $rel = substr($file->getPathname(), strlen(public_path('hop-assets/v8')) + 1);
        $twin = public_path('hop-assets/v9/'.$rel);
        expect(is_file($twin))->toBeTrue("v9 lacks {$rel}");
        if (hash_file('sha256', $file->getPathname()) !== hash_file('sha256', $twin)) {
            $changed[] = $rel;
        }
    }
    sort($changed);
    expect($changed)->toBe(['hop-bento.css', 'hop-brand.css', 'hop-float.css', 'hop-float.js', 'hop-hero3d.js', 'hop-home.js']);
});

test('BioHop uses its official purple document mark everywhere', function () {
    $mark = file_get_contents(hopAssetPath(collect(hopAssets()['apps'])->firstWhere('slug', 'biohop')['icons']['mark']['url']));

    expect($mark)->toContain('#d946ef')->toContain('#8b5cf6');
    expect(file_get_contents(public_path('hop-assets/v9/hop-float.js')))->not->toContain('leaf');
});

test('fonts.css only points at fonts that ship with it', function () {
    $css = file_get_contents(hopAssetPath(hopAssets()['assets']['fonts_css']['url']));
    preg_match_all('/url\(([^)]+)\)/', $css, $m);

    expect($m[1])->not->toBeEmpty();
    foreach ($m[1] as $rel) {
        expect(is_file(public_path('hop-assets/v9/'.$rel)))->toBeTrue("missing font {$rel}");
    }
    expect($css)->toContain("font-family: 'Inter'")
        ->toContain("font-family: 'Instrument Serif'")
        ->toContain("font-family: 'JetBrains Mono'")
        ->toContain('--hop-font-body');
});
