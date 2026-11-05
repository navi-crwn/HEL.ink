<?php

/*
 * The language is set first in the web group, so a failure raised by the CSRF check,
 * the throttle or route binding still reaches the visitor in their language.
 */

function enforceCsrf(): void
{
    // The CSRF check skips itself in the testing environment.
    app()->detectEnvironment(fn () => 'local');
}

test('a real 429 is in the language of the query', function () {
    for ($i = 0; $i < 30; $i++) {
        $this->get('/csrf-token?lang=de')->assertOk();
    }

    app()->setLocale('en'); // The earlier requests in this process set it; a real request starts fresh.
    $html = $this->get('/csrf-token?lang=de')->assertStatus(429)->getContent();

    expect($html)->toContain('<html lang="de"')->and($html)->toContain('<title>'.__('Too many requests', [], 'de'));
});

test('a real 429 is in the language of the cookie', function () {
    for ($i = 0; $i < 30; $i++) {
        $this->withUnencryptedCookie('hop_lang', 'ru')->get('/csrf-token');
    }

    app()->setLocale('en');
    $html = $this->withUnencryptedCookie('hop_lang', 'ru')->get('/csrf-token')->assertStatus(429)->getContent();

    expect($html)->toContain('<html lang="ru"');
});

test('a 404 from route binding is in the language of the visitor', function (string $locale) {
    $html = $this->withoutVite()->get('/announcements/99999?lang='.$locale)->assertNotFound()->getContent();

    expect($html)->toContain('<html lang="'.$locale.'"')->and($html)->toContain(__('Page not found', [], $locale));
})->with(['de', 'ru', 'id']);

test('an expired form token gives the notice in the language of the visitor', function (string $locale) {
    enforceCsrf();

    $response = $this->withoutVite()->withUnencryptedCookie('hop_lang', $locale)->from('/register')
        ->post('/register', ['_token' => 'wrong', 'name' => 'A']);

    $response->assertRedirect('/register')->assertSessionHas('session_expired', __('Your session expired. Try again.', [], $locale));
})->with(['de', 'ru', 'id']);

test('signing out with an expired token gives the notice in the language of the visitor', function () {
    enforceCsrf();

    $this->withUnencryptedCookie('hop_lang', 'de')->post('/logout', ['_token' => 'wrong'])
        ->assertRedirect(route('login'))->assertSessionHas('status', __('You have been signed out.', [], 'de'));
});

test('a language picked in the query is still remembered when the page fails', function () {
    $response = $this->withoutVite()->get('/announcements/99999?lang=de')->assertNotFound();

    expect(collect($response->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === 'hop_lang')?->getValue())->toBe('de');
});

test('a normal page keeps its language and remembers it', function () {
    $response = $this->withoutVite()->get('/privacy?lang=fr')->assertOk();

    expect($response->getContent())->toContain('<html lang="fr"');
    expect(collect($response->headers->getCookies())->map->getName()->all())->toContain('hop_lang');
});
