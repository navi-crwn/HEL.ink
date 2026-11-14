<?php

use App\Models\Announcement;
use App\Models\User;

/*
 * The hub dashboard shows one announcement card, like the other apps: a critical one first (no Hide button), else the
 * newest the member has not hidden for a week (Hide uses the reminder mechanism, key ann-<id>). The public pages
 * /announcements and /announcements/{id} follow the visitor's language.
 */

function annDashboard(User $user): string
{
    return test()->withoutVite()->actingAs($user)->withSession(['2fa_verified' => true])->get('/dashboard')->assertOk()->getContent();
}

function annMake(string $title, array $over = []): Announcement
{
    return Announcement::create($over + ['title' => $title, 'body' => 'Body of '.$title, 'level' => 'info', 'published_at' => now()->subHour()]);
}

function annCards(string $html): int
{
    return substr_count($html, 'id="announcement-title"');
}

test('with three announcements live the dashboard shows exactly one card and a link to the list', function () {
    annMake('One', ['published_at' => now()->subHours(3)]);
    annMake('Two', ['published_at' => now()->subHours(2)]);
    annMake('Three', ['published_at' => now()->subHour()]);
    $html = annDashboard(User::factory()->create());
    expect(annCards($html))->toBe(1)
        ->and($html)->toContain('Three')->and($html)->not->toContain('>Two<')->and($html)->not->toContain('>One<')
        ->and($html)->toContain(route('announcements.index'))->and($html)->toContain('All announcements');
});

test('with one announcement there is one card and no link to the list', function () {
    annMake('Only one');
    $html = annDashboard(User::factory()->create());
    expect(annCards($html))->toBe(1)->and($html)->not->toContain('All announcements');
});

test('with none there is no card', function () {
    expect(annCards(annDashboard(User::factory()->create())))->toBe(0);
});

test('a critical announcement comes first and has no Hide button', function () {
    annMake('Newest info', ['published_at' => now()->subMinutes(5)]);
    $critical = annMake('Pixels are down', ['level' => 'critical', 'app_slug' => 'pixelhop', 'published_at' => now()->subHours(5)]);
    $html = annDashboard(User::factory()->create());
    expect(annCards($html))->toBe(1)->and($html)->toContain('Pixels are down')->and($html)->toContain('PixelHop');
    expect($html)->not->toContain('data-hop-remind="ann-'.$critical->id.'"');
});

test('a normal announcement can be hidden for a week with the reminder mechanism, then comes back', function () {
    $user = User::factory()->create();
    $old = annMake('Older', ['published_at' => now()->subHours(2)]);
    $new = annMake('Newer');
    $html = annDashboard($user);
    expect($html)->toContain('data-hop-remind="ann-'.$new->id.'"')->and($html)->toContain('data-hop-remind-url=');

    test()->actingAs($user)->withSession(['2fa_verified' => true])->postJson('/reminders/dismiss', ['key' => 'ann-'.$new->id])->assertOk()->assertJsonPath('ok', true);
    $html = annDashboard($user);
    expect($html)->not->toContain('Newer')->and($html)->toContain('Older');
    test()->actingAs($user)->withSession(['2fa_verified' => true])->postJson('/reminders/dismiss', ['key' => 'ann-'.$old->id])->assertOk();
    expect(annCards(annDashboard($user)))->toBe(0);

    // Somebody else still sees it, and after a week it comes back.
    expect(annDashboard(User::factory()->create()))->toContain('Newer');
    test()->travel(8)->days();
    $again = test()->withoutVite()->actingAs($user)->withSession(['2fa_verified' => true, 'last_activity_time' => now()])->get('/dashboard')->assertOk()->getContent();
    expect($again)->toContain('Newer');
});

test('a critical announcement cannot be hidden, and neither can an unknown, expired or malformed key', function () {
    $user = User::factory()->create();
    $critical = annMake('Critical', ['level' => 'critical']);
    $expired = annMake('Expired', ['published_at' => now()->subDays(2), 'expires_at' => now()->subHour()]);
    $draft = Announcement::create(['title' => 'Draft', 'body' => 'x', 'level' => 'info']);
    $post = fn (string $key) => test()->actingAs($user)->withSession(['2fa_verified' => true])->postJson('/reminders/dismiss', ['key' => $key]);
    foreach (['ann-'.$critical->id, 'ann-'.$expired->id, 'ann-'.$draft->id, 'ann-999999', 'ann-', 'ann-abc', 'ann-1x', 'ann--1', 'ann-1234567890123456789', 'ANN-1', 'nonsense'] as $key) {
        $post($key)->assertStatus(422);
    }
    expect(annCards(annDashboard($user)))->toBe(1);
});

test('a guest cannot hide anything', function () {
    $a = annMake('A');
    test()->postJson('/reminders/dismiss', ['key' => 'ann-'.$a->id])->assertUnauthorized();
});

test('the existing reminder keys still work and the dashboard delete button goes to the delete section', function () {
    $user = User::factory()->create();
    test()->actingAs($user)->withSession(['2fa_verified' => true])->postJson('/reminders/dismiss', ['key' => 'two-factor'])->assertOk();
    expect(annDashboard($user))->toContain('#delete-account')->and(annDashboard($user))->toMatch('~href="[^"]*#delete-account"[^>]*>\s*Delete account~');
});

test('the public announcement pages follow the language', function () {
    $a = annMake('Maintenance');
    $list = test()->withoutVite()->get('/announcements?lang=id')->assertOk()->getContent();
    expect($list)->toContain('Pengumuman HOP')->and($list)->toContain('Pengumuman')
        ->and($list)->toContain('Perubahan pada app HOP mana pun diumumkan di sini lebih dulu')
        ->and($list)->not->toContain('Changes to any HOP app are announced here first');
    $detail = test()->withoutVite()->get('/announcements/'.$a->id.'?lang=id')->assertOk()->getContent();
    expect($detail)->toContain('Semua pengumuman')->and($detail)->not->toContain('All announcements');

    Announcement::query()->delete();
    expect(test()->withoutVite()->get('/announcements?lang=id')->getContent())->toContain('Belum ada pengumuman saat ini.')->not->toContain('Nothing announced right now.');
    expect(test()->withoutVite()->get('/announcements')->getContent())->toContain('Nothing announced right now.');
});

test('the new labels exist in all nine languages and are translated', function () {
    $keys = ['HOP announcements', 'Changes to any HOP app are announced here first, as the terms promise.', 'Nothing announced right now.', 'All announcements', 'Draft'];
    foreach (['en', 'zh-CN', 'de', 'id', 'ja', 'pt-BR', 'es', 'fr', 'ru'] as $locale) {
        $map = json_decode(file_get_contents(lang_path($locale.'.json')), true);
        foreach ($keys as $key) {
            expect($map[$key] ?? '')->not->toBe('');
            if ($locale !== 'en') {
                expect($map[$key])->not->toBe($key);
            }
        }
    }
});
