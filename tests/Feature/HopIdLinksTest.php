<?php

use App\Models\User;

/**
 * Where the dashboard chrome sends a signed-in person: the HOP ID wordmark goes to the
 * dashboard (not the marketing home page), only links that open a new tab carry the ↗ mark.
 */
test('the sidebar wordmark links to the dashboard, not the public home page', function () {
    $this->actingAs(User::factory()->create());

    $html = $this->get('/dashboard')->assertOk()->getContent();

    expect($html)->toContain('href="'.route('dashboard').'" class="flex items-center gap-2.5"');
    // The home page link is for guests (guest layout); the signed-in sidebar must not have it.
    preg_match('#<aside.*?</aside>#s', $html, $aside);
    expect($aside[0] ?? '')->not->toContain('href="'.route('home').'"');
});

test('the app list in the sidebar is labelled HOP family', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/dashboard')->assertOk()->assertSee('HOP family');
    $this->get('/dashboard?lang=id')->assertOk()->assertSee('Keluarga HOP');
});

test('only "Visit site" opens a new tab and only it is marked with the arrow', function () {
    $this->actingAs(User::factory()->create());

    $html = $this->get('/dashboard')->assertOk()->getContent();
    preg_match_all('#<a href="[^"]*/apps/[a-z]+"[^>]*>(.*?)</a>#s', $html, $open);
    preg_match_all('#<a href="https://[a-z.]+"[^>]*target="_blank"[^>]*>(.*?)</a>#s', $html, $visit);

    expect($open[0])->not->toBeEmpty()->and($visit[0])->not->toBeEmpty();
    foreach ($open[1] as $label) {
        expect($label)->not->toContain('↗')->not->toContain('M7 17 17 7');
    }
    foreach ($visit[1] as $label) {
        expect($label)->toContain('M7 17 17 7M8 7h9v9')->toContain('opens in a new tab');
    }
    foreach ($open[0] as $tag) {
        expect($tag)->not->toContain('target="_blank"');
    }
});
