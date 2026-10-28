<?php

use App\Http\Controllers\SsoController;
use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');
    $response->assertStatus(200);
});
test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();
    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);
    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});
test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();
    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);
    $this->assertGuest();
});
test('users can logout', function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->post('/logout');
    $this->assertGuest();
    // Logout chains through the apps' logout endpoints (SsoController::logoutChain) and ends at the home page.
    $response->assertRedirect(SsoController::logoutChain(route('home')));
});
