<?php

use App\Models\User;

/*
 * Markup defects found by html-validate on the rendered hub pages (A2-S3-17). The <style> inside <div>
 * errors come from packages/ui (switcher-assets, account-menu) and are not covered here.
 */

function hubPage($test, string $uri, ?User $user = null): string
{
    $req = $test;
    if ($user) {
        $req = $req->actingAs($user)->withSession(['2fa_verified' => true]);
    }

    return $req->get($uri)->assertOk()->getContent();
}

beforeEach(fn () => $this->withoutVite());

test('hidden CSRF inputs carry no autocomplete attribute', function () {
    expect(hubPage($this, '/login'))
        ->toContain('name="_token"')
        ->not->toMatch('/<input[^>]*type="hidden"[^>]*autocomplete=/i');
});

test('the FAQ lists are not <dl> elements holding <details>', function () {
    $html = hubPage($this, '/faq');

    expect($html)->toContain('<details')->not->toContain('<dl');
});

test('the two-factor challenge does not nest the sign-out form inside the code form', function () {
    $user = User::factory()->create(['two_factor_secret' => encrypt('ABCDEFGHIJKLMNOP'), 'two_factor_recovery_codes' => encrypt('[]'), 'two_factor_confirmed_at' => now()]);
    $html = $this->withoutVite()->actingAs($user)->get('/two-factor-challenge')->assertOk()->getContent();

    $open = 0;
    $deepest = 0;
    preg_match_all('#<form\b|</form>#i', $html, $m);
    foreach ($m[0] as $tag) {
        $open += strtolower($tag) === '</form>' ? -1 : 1;
        $deepest = max($deepest, $open);
    }
    expect($deepest)->toBe(1)->and($html)->toContain(route('logout'));
});

test('forms have a submit button and the announcement title input has a type', function () {
    $user = User::factory()->create();
    expect(hubPage($this, '/profile', $user))->not->toContain('id="send-verification"');
    expect(hubPage($this, '/settings', $user))->toMatch('#<form[^>]*profile/avatar[^>]*>.*?<button[^>]*type="submit"#s');
    expect(hubPage($this, '/admin/announcements', User::factory()->admin()->create()))->toContain('id="a-title" name="title" type="text"');
});

test('login, register and password reset carry an og:title and the sign-in notices an h1', function () {
    foreach (['/login', '/register', '/forgot-password'] as $uri) {
        expect(hubPage($this, $uri))->toContain('property="og:title"');
    }
    expect(hubPage($this, '/suspended'))->toContain('<h1');
    expect(hubPage($this, '/verify-email', User::factory()->unverified()->create()))->toContain('<h1');
});

test('signed-in page titles read "Section · HOP" like the public pages', function () {
    $user = User::factory()->create();
    foreach (['/settings' => 'Settings', '/api-docs' => 'API', '/dashboard' => 'Launcher'] as $uri => $name) {
        expect(hubPage($this, $uri, $user))->toContain("<title>{$name} · HOP</title>");
    }
});
