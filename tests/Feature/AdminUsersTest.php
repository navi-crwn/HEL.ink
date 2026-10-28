<?php

use App\Models\User;

test('admins can list, suspend, reinstate and delete accounts', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $this->withoutVite()->actingAs($admin)->withSession(['2fa_verified' => true])->get('/admin/users?q='.$user->email)->assertOk()->assertSee($user->email);
    $this->actingAs($admin)->withSession(['2fa_verified' => true])->post("/admin/users/{$user->id}/suspend")->assertRedirect();
    expect($user->fresh()->isSuspended())->toBeTrue();
    $this->actingAs($admin)->withSession(['2fa_verified' => true])->post("/admin/users/{$user->id}/activate")->assertRedirect();
    expect($user->fresh()->isSuspended())->toBeFalse();
    $this->actingAs($admin)->withSession(['2fa_verified' => true])->delete("/admin/users/{$user->id}")->assertRedirect(route('admin.users.index'));
    expect($user->fresh())->toBeNull();
});

test('ordinary users are kept out', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['2fa_verified' => true])->get('/admin/users')->assertForbidden();
});

test('guests are sent to login', function () {
    $this->get('/admin/users')->assertRedirect('/login');
});

test('the owner cannot be suspended', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->owner()->create();
    $this->actingAs($admin)->withSession(['2fa_verified' => true])->from('/admin/users/'.$owner->id)->post("/admin/users/{$owner->id}/suspend")->assertSessionHasErrors('user');
    expect($owner->fresh()->isSuspended())->toBeFalse();
});
