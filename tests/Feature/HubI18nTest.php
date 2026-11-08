<?php

use App\Models\User;
use Hop\Ui\Locales;

/**
 * The parts of the hub the 2026-11-08 test round found in English in every language:
 * Settings, the sidebar, the account menu and the sign-in pages (which also had no
 * language menu), plus the FAQ's language answer.
 */
test('settings is translated and the sidebar labels follow the language', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/settings?lang=id')->assertOk()
        ->assertSee('Pengaturan')
        ->assertSee('Riwayat masuk')
        ->assertSee('Token API')
        ->assertSee('Profil HOP ID')
        ->assertSee('Peluncur')
        ->assertDontSee('Login History')
        ->assertDontSee('HOP ID profile');

    $this->get('/settings?lang=ru')->assertOk()->assertSee('История входов')->assertDontSee('Login history');
});

test('every settings and sign-in string has a translation in the nine catalogs', function () {
    $keys = [
        'Settings', 'Login history', 'API tokens', 'HOP ID profile', 'Delete my account', 'Two-factor authentication',
        'Welcome back', 'Continue with Google', 'Create your HOP ID', 'Reset your HOP ID password', 'Sign out', 'Account (HOP ID)',
        'Launcher', 'Announcements', 'Status',
    ];
    foreach (Locales::codes() as $lang) {
        $data = json_decode(file_get_contents(base_path("lang/{$lang}.json")), true);
        foreach ($keys as $key) {
            expect(array_key_exists($key, $data))->toBeTrue("{$lang}.json lacks \"{$key}\"");
        }
    }
});

test('the sign-in pages have the nine-language menu and follow ?lang=', function () {
    $html = $this->get('/login')->assertOk()->getContent();
    expect($html)->toContain('hop-langmenu');
    foreach (Locales::codes() as $code) {
        expect($html)->toContain('hreflang="'.$code.'"');
    }
    expect($html)->not->toContain('hreflang="ar"');

    $this->get('/login?lang=id')->assertOk()->assertSee('Selamat datang kembali')->assertSee('Lanjutkan dengan Google')->assertDontSee('Welcome back');
    $this->get('/register?lang=de')->assertOk()->assertSee(trans('Create your HOP ID', [], 'de'))->assertDontSee('Create your HOP ID');
    $this->get('/forgot-password?lang=ja')->assertOk()->assertSee(trans('Reset your HOP ID password', [], 'ja'));
});

test('the FAQ names all nine languages and never Arabic, in every language', function () {
    $names = ['English', '简体中文', 'Deutsch', 'Bahasa Indonesia', '日本語', 'Português', 'Español', 'Français', 'Русский'];
    foreach (Locales::codes() as $lang) {
        $html = $this->get('/faq?lang='.$lang)->assertOk()->getContent();
        foreach ($names as $name) {
            expect($html)->toContain($name);
        }
        expect($html)->not->toContain('eight languages')->not->toContain('8 languages')->not->toContain('العربية')->not->toContain('Arabic');
    }
});
