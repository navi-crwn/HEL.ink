<?php

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/*
 * GET /announcements.json: the read-only family feed (contract L-1). Public, no cookie, version 1, only what is live,
 * newest first, at most 20, ?app= keeps the announcements for every app and for that one app.
 */

function feedItems(array $query = [], string $host = 'https://hel.ink'): array
{
    $res = test()->getJson($host.'/announcements.json'.($query ? '?'.http_build_query($query) : ''))->assertOk();

    return $res->json('items');
}

test('the feed has the contract shape and only what is live, newest first', function () {
    config()->set('hop.public_url', 'https://hel.ink');
    $old = Announcement::create(['title' => 'Older', 'body' => 'Body one', 'level' => 'info', 'published_at' => now()->subDays(2)]);
    $new = Announcement::create(['title' => 'Newer', 'body' => 'Body two', 'level' => 'critical', 'app_slug' => 'pixelhop', 'published_at' => now()->subHour(), 'expires_at' => now()->addDay()]);
    Announcement::create(['title' => 'Draft', 'body' => 'x', 'level' => 'info']);
    Announcement::create(['title' => 'Future', 'body' => 'x', 'level' => 'info', 'published_at' => now()->addDay()]);
    Announcement::create(['title' => 'Expired', 'body' => 'x', 'level' => 'info', 'published_at' => now()->subDays(3), 'expires_at' => now()->subMinute()]);

    $res = test()->getJson('/announcements.json')->assertOk()->assertHeader('Content-Type', 'application/json');
    $json = $res->json();
    expect($json['version'])->toBe(1)
        ->and($json['generated_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/')
        ->and(array_column($json['items'], 'title'))->toBe(['Newer', 'Older']);
    expect($json['items'][0])->toBe([
        'id' => (string) $new->id,
        'level' => 'critical',
        'title' => 'Newer',
        'body' => 'Body two',
        'url' => 'https://hel.ink/announcements/'.$new->id,
        'published_at' => $new->published_at->utc()->format('Y-m-d\TH:i:s\Z'),
        'expires_at' => $new->expires_at->utc()->format('Y-m-d\TH:i:s\Z'),
        'apps' => ['pixelhop'],
    ]);
    expect($json['items'][1]['expires_at'])->toBeNull()->and($json['items'][1]['apps'])->toBe(['*'])->and($json['items'][1]['id'])->toBe((string) $old->id);
    // Nothing personal: exactly these keys, no author.
    expect(array_keys($json['items'][0]))->toBe(['id', 'level', 'title', 'body', 'url', 'published_at', 'expires_at', 'apps']);
});

test('?app= keeps the announcements for every app and for that app only', function () {
    Announcement::create(['title' => 'Everyone', 'body' => 'x', 'level' => 'info', 'published_at' => now()->subHours(3)]);
    Announcement::create(['title' => 'Pixels', 'body' => 'x', 'level' => 'info', 'app_slug' => 'pixelhop', 'published_at' => now()->subHours(2)]);
    Announcement::create(['title' => 'Notes', 'body' => 'x', 'level' => 'info', 'app_slug' => 'notehop', 'published_at' => now()->subHour()]);

    expect(array_column(feedItems(), 'title'))->toBe(['Notes', 'Pixels', 'Everyone']);
    expect(array_column(feedItems(['app' => 'pixelhop']), 'title'))->toBe(['Pixels', 'Everyone']);
    expect(array_column(feedItems(['app' => 'notehop']), 'title'))->toBe(['Notes', 'Everyone']);
    expect(array_column(feedItems(['app' => 'filehop']), 'title'))->toBe(['Everyone']);
    // Not a usable slug (a list, odd characters): no match for any app, only the announcements for everybody.
    expect(array_column(feedItems(['app' => "x' OR 1=1 --"]), 'title'))->toBe(['Everyone']);
    test()->getJson('/announcements.json?app[]=pixelhop')->assertOk();
});

test('HTML is dropped from the title and the body', function () {
    Announcement::create(['title' => '<b>Bold</b> <script>alert(1)</script>title', 'body' => "<p>Line <a href=\"https://evil.example\">one</a></p>\n<img src=x onerror=alert(1)>Line two", 'level' => 'info', 'published_at' => now()->subHour()]);
    $item = feedItems()[0];
    expect($item['title'])->not->toContain('<')->and($item['body'])->not->toContain('<')->and($item['body'])->toContain('Line one')->and($item['body'])->toContain('Line two');
});

test('at most 20 items', function () {
    for ($i = 0; $i < 23; $i++) {
        Announcement::create(['title' => 'A'.$i, 'body' => 'x', 'level' => 'info', 'published_at' => now()->subMinutes($i + 1)]);
    }
    expect(feedItems())->toHaveCount(20);
});

test('the answer is public and cacheable for five minutes, and carries no cookie and no Vary: Cookie', function () {
    Announcement::create(['title' => 'A', 'body' => 'x', 'level' => 'info', 'published_at' => now()->subHour()]);
    $res = test()->get('/announcements.json')->assertOk();
    expect($res->headers->get('Cache-Control'))->toBe('max-age=300, public');
    expect($res->headers->getCookies())->toBe([]);
    expect($res->headers->has('Set-Cookie'))->toBeFalse();
    expect((string) $res->headers->get('Vary'))->not->toContain('Cookie');
});

test('a visitor with cookies and a signed-in member get the same cookie-free answer', function () {
    $user = User::factory()->create();
    $res = test()->actingAs($user)->withUnencryptedCookie('hop_lang', 'id')->get('/announcements.json')->assertOk();
    expect($res->headers->getCookies())->toBe([]);
    expect($res->headers->get('Cache-Control'))->toContain('public');
});

test('it answers on the public host and on the hub host', function () {
    Announcement::create(['title' => 'A', 'body' => 'x', 'level' => 'info', 'published_at' => now()->subHour()]);
    expect(feedItems([], 'https://'.config('hop.public_host')))->toHaveCount(1);
    expect(feedItems([], 'https://'.config('hop.hub_host')))->toHaveCount(1);
});

test('with the database down it is still 200 with an empty list', function () {
    Schema::drop('announcements');
    $res = test()->get('/announcements.json')->assertOk();
    expect($res->json('items'))->toBe([])->and($res->json('version'))->toBe(1);
    expect($res->headers->get('Cache-Control'))->toContain('public');
    expect($res->headers->getCookies())->toBe([]);
});

test('an announcement that expires leaves the feed and an unpublished one never enters it', function () {
    $a = Announcement::create(['title' => 'A', 'body' => 'x', 'level' => 'info', 'published_at' => now()->subHour(), 'expires_at' => now()->addMinute()]);
    expect(feedItems())->toHaveCount(1);
    test()->travel(2)->minutes();
    expect(feedItems())->toBe([]);
    expect($a->fresh()->isLive())->toBeFalse();
});

test('POST and other methods are not answered', function () {
    test()->post('/announcements.json')->assertStatus(405);
});
