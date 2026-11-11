<?php

use App\Models\User;

test('guests cannot open or submit onboarding', function () {
    $this->get('/onboarding')->assertRedirect('/login');
    $this->post('/onboarding/skip')->assertRedirect('/login');
});

test('a signed-in user can render onboarding', function () {
    $this->actingAs(User::factory()->create())
        ->get('/onboarding')
        ->assertOk();
});

test('the goodbye page stays public', function () {
    $this->get('/goodbye')->assertOk();
});
