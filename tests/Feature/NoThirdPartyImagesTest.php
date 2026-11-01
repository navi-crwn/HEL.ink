<?php

test('a country is shown as its code, never as a picture from another host', function () {
    $html = country_flag('id');
    expect($html)->toContain('ID')->toContain('title="Indonesia"')->not->toContain('<img')->not->toContain('flagcdn');
    expect(country_flag(null))->toContain('Unknown');
});

test('no view or helper names an image, script or font CDN', function () {
    $hosts = ['flagcdn.com', 'cdn.jsdelivr.net', 'unpkg.com', 'cdn.tailwindcss.com', 'fonts.googleapis.com', 'cdn.simpleicons.org'];
    $roots = [base_path('app'), base_path('resources/views'), base_path('resources/js')];
    foreach ($roots as $root) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
            if (! preg_match('/\.(php|js)$/', $file->getFilename())) {
                continue;
            }
            $text = file_get_contents($file->getPathname());
            foreach ($hosts as $host) {
                expect($text)->not->toContain($host, $file->getPathname());
            }
        }
    }
});
