<?php

use App\Support\FamilyStory;

/** Local copies of the family list must follow the hop-assets manifest, so adding an app cannot skip one. */
function g0_public_apps(): array
{
    $manifest = json_decode(file_get_contents(public_path('hop-assets/apps.json')), true);

    return array_values(array_filter($manifest['apps'], fn ($app) => ($app['public'] ?? false) === true));
}

test('FamilyStory::APPS lists every public app except the hub, in manifest order', function () {
    $slugs = array_values(array_map(fn ($a) => $a['slug'], array_filter(g0_public_apps(), fn ($a) => $a['slug'] !== 'hub')));

    expect(FamilyStory::APPS)->toBe($slugs);
});

test('hop.github.repos lists the repository of every public app', function () {
    $repos = array_map(fn ($a) => str_replace('https://github.com/', '', $a['repo']), g0_public_apps());

    expect(config('hop.github.repos'))->toBe($repos);
});

test('the changelog builder PUBLIC list matches the public apps', function () {
    preg_match('/^PUBLIC = \{(.*)\}$/m', file_get_contents(base_path('scripts/build-changelog.py')), $m);
    preg_match_all('/"([^"]+)": "([^"]+)"/', $m[1], $pairs, PREG_SET_ORDER);
    $listed = [];
    foreach ($pairs as $p) {
        $listed[$p[1]] = $p[2];
    }
    $expected = [];
    foreach (g0_public_apps() as $app) {
        $expected[basename($app['repo'])] = $app['name'];
    }

    expect($listed)->toBe($expected);
});
