<?php

use App\Models\User;
use Hop\Ui\Apps;

test('the app list comes from config/hop.php with the current app marked', function () {
    config()->set('hop.apps.biohop.enabled', false);
    $apps = Apps::all();
    expect(array_column($apps, 'slug'))->toBe(['hub', 'linkhop', 'pixelhop']);
    expect($apps[0]['current'])->toBeTrue();
    expect($apps[1]['open'])->toBe(rtrim(config('hop.hub_url'), '/').'/apps/linkhop');
});

test('the pill and nav items render in the hub layout', function () {
    $user = User::factory()->create();
    $this->withoutVite()->actingAs($user)->withSession(['2fa_verified' => true])->get('/dashboard')
        ->assertOk()->assertSee('hop-pill')->assertSee('PixelHop');
});
