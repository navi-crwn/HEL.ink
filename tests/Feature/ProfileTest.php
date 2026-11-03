<?php

use App\Models\User;
use App\Support\Avatar;
use Illuminate\Http\UploadedFile;

test('profile page is displayed', function () {
    $user = User::factory()->create();
    $response = $this
        ->actingAs($user)
        ->get('/profile');
    $response->assertOk();
});
test('profile information can be updated', function () {
    $user = User::factory()->create();
    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');
    $user->refresh();
    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});
test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();
    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);
    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');
    $this->assertNotNull($user->refresh()->email_verified_at);
});
test('user can delete their account', function () {
    $user = User::factory()->create();
    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);
    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('goodbye'));
    $this->assertGuest();
    $this->assertNull($user->fresh());
});
test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();
    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->delete('/profile', [
            'password' => 'wrong-password',
        ]);
    $response
        ->assertSessionHasErrorsIn('userDeletion', 'password')
        ->assertRedirect('/profile');
    $this->assertNotNull($user->fresh());
});
test('profile update rejects external or malformed _back redirect targets', function () {
    $user = User::factory()->create();

    foreach (['https://evil.example/phish', '//evil.example', '/\\evil.example'] as $badBack) {
        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Safe User',
                'email' => $user->email,
                '_back' => $badBack,
            ])
            ->assertRedirect(route('profile.edit'));
    }

    $this->actingAs($user)
        ->patch('/profile', [
            'name' => 'Safe User',
            'email' => $user->email,
            '_back' => route('settings', [], false).'#account',
        ])
        ->assertRedirect('/settings#account');
});

test('the avatar limit shown on the settings page and enforced by the form is what PHP will accept', function () {
    $kb = min(6144, intdiv(UploadedFile::getMaxFilesize(), 1024));

    expect(Avatar::maxKb())->toBe($kb);
    $this->withoutVite()->actingAs(User::factory()->create())->withSession(['2fa_verified' => true])
        ->get('/settings')->assertOk()
        ->assertSee('up to '.($kb >= 1024 ? round($kb / 1024, 1).' MB' : $kb.' KB'), false)
        ->assertDontSee('up to 6 MB', $kb < 6144);
});
