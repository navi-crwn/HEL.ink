<?php

use App\Models\User;
use App\Support\Avatar;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

/** A valid 1-bit greyscale PNG: small on disk, large once decoded. */
function blankPng(int $width, int $height): string
{
    $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    $row = "\0".str_repeat("\0", (int) ceil($width / 8));

    return "\x89PNG\r\n\x1a\n"
        .$chunk('IHDR', pack('NNCCCCC', $width, $height, 1, 0, 0, 0, 0))
        .$chunk('IDAT', gzcompress(str_repeat($row, $height), 9))
        .$chunk('IEND', '');
}

test('an avatar with more pixels than the limit is refused before it is decoded', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $bomb = UploadedFile::fake()->createWithContent('bomb.png', blankPng(7500, 7500));

    $this->actingAs($user)->withSession(['2fa_verified' => true])->from('/settings')
        ->post('/profile/avatar', ['avatar' => $bomb])
        ->assertRedirect('/settings')->assertSessionHasErrors('avatar');

    expect($user->fresh()->avatar_path)->toBeNull();
    expect(Storage::disk('public')->allFiles('avatars'))->toBe([]);
});

test('an ordinary avatar is still accepted', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->withSession(['2fa_verified' => true])->from('/settings')
        ->post('/profile/avatar', ['avatar' => UploadedFile::fake()->createWithContent('a.png', blankPng(1000, 800))])
        ->assertRedirect('/settings')->assertSessionHasNoErrors();

    expect($user->fresh()->avatar_path)->toStartWith('avatars/');
});

test('avatar uploads are rate limited', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create())->withSession(['2fa_verified' => true]);
    $file = fn () => UploadedFile::fake()->createWithContent('a.png', blankPng(10, 10));
    for ($i = 0; $i < 20; $i++) {
        $this->post('/profile/avatar', ['avatar' => $file()])->assertRedirect();
    }

    $this->post('/profile/avatar', ['avatar' => $file()])->assertStatus(429);
});
