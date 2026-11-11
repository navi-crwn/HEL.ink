<?php

use Hop\Ui\Assets;
use Illuminate\Support\Facades\Blade;

test('the family nav lists HOP ID and every other public app with its mark, not the current app', function () {
    $html = Blade::render('<x-hop::family-nav app="biohop" />');
    foreach (['HOP ID', 'LinkHop', 'PixelHop', 'NoteHop'] as $name) {
        expect($html)->toContain('<span class="flex-1">'.$name.'</span>');
    }
    expect($html)->not->toContain('<span class="flex-1">BioHop</span>')
        ->and(substr_count($html, 'https://hel.ink/hop-assets/'))->toBe(count(Assets::family('biohop')) - 1);
});

test('an app this one has no SSO start for opens through the hub launcher, never by its bare address', function () {
    config()->set('hop.current', 'biohop');
    config()->set('hop.hub_url', 'https://hub.hel.ink');
    config()->set('hop.apps', ['linkhop' => ['url' => 'https://link.hel.ink', 'start' => '/auth/helink']]);
    $html = Blade::render('<x-hop::family-nav app="biohop" />');
    expect($html)->toContain('href="https://hub.hel.ink/apps/filehop"')
        ->and($html)->not->toContain('href="https://file.hel.ink"')
        ->and($html)->toContain('href="https://hub.hel.ink/dashboard"');
});

test('the account menu shows who is signed in and offers account, settings and sign out', function () {
    $user = (object) ['name' => 'Mira Studio', 'email' => 'mira@example.test'];
    $html = Blade::render('<x-hop::account-menu :user="$user" settings-url="/settings" account-url="https://hub.hel.ink/settings" logout-url="/logout" />', ['user' => $user]);
    expect($html)->toContain('Mira Studio')->toContain('mira@example.test')
        ->toContain('href="/settings"')->toContain('href="https://hub.hel.ink/settings"')->toContain('action="/logout"');
});

test('the dropdown styles come once even when several switchers are on the page', function () {
    $user = (object) ['name' => 'A', 'email' => 'a@example.test'];
    $html = Blade::render('<x-hop::language-switcher /><x-hop::app-switcher app="biohop" /><x-hop::account-menu :user="$user" />', ['user' => $user]);
    expect(substr_count($html, '.hop-switcher{position:relative'))->toBe(1);
});
