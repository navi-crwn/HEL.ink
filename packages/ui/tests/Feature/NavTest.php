<?php

use App\Models\User;
use Hop\Ui\Apps;

test('the app list comes from config/hop.php with the current app marked', function () {
    config()->set('hop.apps.biohop.enabled', false);
    config()->set('hop.apps.notehop.enabled', false);
    $apps = Apps::all();
    expect(array_column($apps, 'slug'))->toBe(['hub', 'linkhop', 'pixelhop']);
    expect($apps[0]['current'])->toBeTrue();
    expect($apps[1]['open'])->toBe(rtrim(config('hop.hub_url'), '/').'/apps/linkhop');
});

test('the hub sidebar lists the apps once (no second switcher in the top bar)', function () {
    $user = User::factory()->create();
    $this->withoutVite()->actingAs($user)->withSession(['2fa_verified' => true])->get('/dashboard')
        ->assertOk()->assertDontSee('hop-pill')->assertSee('PixelHop')->assertSee('NoteHop');
});
