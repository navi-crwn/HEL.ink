<?php

use App\Models\HopApp;
use App\Models\User;

/** The foot of the hub sidebar is the same in every HOP dashboard (contract L-9, with Feedback after Help): seven links down, then "Part of Hop Easy Link". */
function sidebarFoot(string $query = ''): string
{
    $html = test()->withoutVite()->actingAs(User::factory()->create())->get('/dashboard'.$query)->assertOk()->getContent();
    preg_match('#<div class="mt-auto pt-3" data-side-foot>(.*?)</aside>#s', $html, $m);

    return $m[1] ?? '';
}

/** The feature is on while an app that may read reports (Imperium, admin_client) is registered. */
function feedbackOn(): void
{
    HopApp::create(['slug' => 'imperium', 'name' => 'Imperium', 'url' => 'https://imperium.example.test', 'callback_path' => '/auth/sso/callback',
        'start_path' => '/login', 'status' => 'active', 'secret' => 'local-test-imperium-secret-long-enough', 'capabilities' => ['admin_client'], 'hidden' => true]);
}

test('the foot lists Help, Feedback, Terms, Privacy, Changelog, GitHub, Announcement in that order, then Part of', function () {
    feedbackOn();
    $foot = sidebarFoot();
    preg_match_all('#<li><a href="([^"]+)"[^>]*>(.*?)</a></li>#', $foot, $m);
    expect($m[2])->toBe(['Help', 'Feedback', 'Terms', 'Privacy', 'Changelog', 'GitHub', 'Announcement']);
    expect(html_entity_decode($m[1][1]))->toStartWith(hub_url('feedback').'?app=hub&from=dashboard');
    expect(array_values(array_diff_key($m[1], [1 => 1])))->toBe([route('help'), route('terms'), route('privacy'), route('changelog'), 'https://github.com/navi-crwn/HEL.ink', route('announcements.index')]);
    expect(strpos($foot, 'data-part-of'))->toBeGreaterThan(strrpos($foot, '</ul>'));
    expect($foot)->toContain('Part of')->toContain('Hop Easy Link');
});

test('without the feature the foot stays the six links it was', function () {
    preg_match_all('#<li><a href="([^"]+)"[^>]*>(.*?)</a></li>#', sidebarFoot(), $m);
    expect($m[2])->toBe(['Help', 'Terms', 'Privacy', 'Changelog', 'GitHub', 'Announcement']);
});

test('the foot is in the one scroll area with the menu, not in a box of its own', function () {
    $html = test()->withoutVite()->actingAs(User::factory()->create())->get('/dashboard')->getContent();
    expect($html)->toMatch('#data-side-scroll>\s*<nav>.*?data-side-foot#s');
});

test('the foot labels follow the language in all nine languages', function () {
    foreach (['en', 'zh-CN', 'de', 'id', 'ja', 'pt-BR', 'es', 'fr', 'ru'] as $locale) {
        $map = json_decode(file_get_contents(lang_path($locale.'.json')), true);
        foreach (['Help', 'Feedback', 'Terms', 'Privacy', 'Changelog', 'Announcement', 'Part of :name'] as $key) {
            expect($map[$key] ?? '')->not->toBe('');
            if ($locale !== 'en' && ! ($key === 'Feedback' && in_array($locale, ['de', 'pt-BR'], true))) { // German and Portuguese say Feedback too
                expect($map[$key])->not->toBe($key);
            }
        }
        expect($map['Part of :name'])->toContain(':name');
    }
    feedbackOn();
    expect(sidebarFoot('?lang=id'))->toContain('Bantuan')->toContain('Masukan')->toContain('Pengumuman')->toContain('Bagian dari')->toContain('Hop Easy Link');
});
