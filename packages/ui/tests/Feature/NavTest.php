<?php

use Hop\Ui\Apps;

test('the app list comes from config/hop.php with the current app marked', function () {
    config()->set('hop.current', 'hub');
    config()->set('hop.hub_url', 'https://hub.hel.ink');
    config()->set('hop.apps', [
        'linkhop' => ['url' => 'https://link.hel.ink'],
        'biohop' => ['url' => 'https://bio.hel.ink', 'enabled' => false],
        'pixelhop' => ['url' => 'https://pixel.hel.ink'],
    ]);
    $apps = Apps::all();
    expect(array_column($apps, 'slug'))->toBe(['hub', 'linkhop', 'pixelhop']);
    expect($apps[0]['current'])->toBeTrue();
    expect($apps[1]['open'])->toBe('https://hub.hel.ink/apps/linkhop');
});
