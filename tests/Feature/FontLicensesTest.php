<?php

// Every font this repository ships must be named in both LICENSE and CREDITS.md,
// with a license file next to the font files.
test('LICENSE and CREDITS.md name every font that has a license file in hop-assets', function () {
    $license = file_get_contents(base_path('LICENSE'));
    $credits = file_get_contents(base_path('CREDITS.md'));
    $files = glob(public_path('hop-assets/v9/fonts/LICENSE-*.txt'));
    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $slug = substr(basename($file, '.txt'), strlen('LICENSE-'));
        $name = ucwords(str_replace('-', ' ', $slug));
        if ($slug === 'jetbrains-mono') {
            $name = 'JetBrains Mono';
        }
        expect($license)->toContain("- {$name} font: SIL Open Font License 1.1");
        expect($credits)->toContain("- {$name}, by");
        expect(file_get_contents($file))->toContain('SIL OPEN FONT LICENSE Version 1.1');
    }
});
