<?php

use App\Models\User;

it('does not let the browser keep a signed-in hub page (Back after logout)', function () {
    $user = User::factory()->create();
    $res = $this->actingAs($user)->get('/dashboard');
    $res->assertOk();
    expect($res->headers->get('Cache-Control'))->toContain('no-store');
});

it('keeps the normal headers for guests', function () {
    $res = $this->get('/login');
    expect($res->headers->get('Cache-Control'))->not->toContain('no-store');
});
