<?php

use App\Models\Announcement;
use App\Models\User;

test('published announcements show on the home page, the launcher and their own pages', function () {
    $live = Announcement::create(['title' => 'Short links move to /l/', 'body' => 'From 27 November…', 'level' => 'warning', 'published_at' => now()->subHour()]);
    $draft = Announcement::create(['title' => 'Secret draft', 'body' => 'x', 'level' => 'info']);
    $this->withoutVite()->get('/')->assertOk()->assertSee('Short links move')->assertDontSee('Secret draft');
    $this->withoutVite()->get('/announcements')->assertOk()->assertSee('Short links move')->assertDontSee('Secret draft');
    $this->withoutVite()->get('/announcements/'.$live->id)->assertOk();
    $this->get('/announcements/'.$draft->id)->assertNotFound();
    $user = User::factory()->create();
    $this->withoutVite()->actingAs($user)->withSession(['2fa_verified' => true])->get('/dashboard')->assertOk()->assertSee('Short links move');
});

test('admins create and publish announcements', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->withSession(['2fa_verified' => true])
        ->post('/admin/announcements', ['title' => 'Maintenance', 'body' => 'Tonight', 'level' => 'info', 'app_slug' => 'linkhop', 'publish_now' => 1])
        ->assertRedirect();
    expect(Announcement::live()->count())->toBe(1);
    $this->withoutVite()->actingAs($admin)->withSession(['2fa_verified' => true])->get('/admin/announcements')->assertOk()->assertSee('Maintenance');
});
