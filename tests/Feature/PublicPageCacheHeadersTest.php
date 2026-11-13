<?php

use App\Models\User;

/*
 * Rule 6 of the SEO and language contract (train 6, X-1): a public page that follows the language cookie says so with
 * `Vary: Cookie`, so a shared cache keeps visitors apart. Public answers that do not follow the cookie (files, the
 * stats numbers) do not get it, and a signed-in page stays no-store.
 */

test('public hub pages are private, no-cache, and vary by cookie', function (string $cookie, string $query) {
    foreach (['/about', '/privacy'] as $path) {
        $req = $this->withoutVite();
        if ($cookie !== '') {
            $req = $req->withUnencryptedCookie('hop_lang', $cookie);
        }
        $res = $req->get('https://hel.ink'.$path.$query)->assertOk();
        $cache = (string) $res->headers->get('Cache-Control');
        expect($cache)->toContain('private')->and($cache)->toContain('no-cache');
        expect(array_map('trim', explode(',', (string) $res->headers->get('Vary'))))->toContain('Cookie');
    }
})->with([
    'no cookie' => ['', ''],
    'language cookie' => ['id', ''],
    'language in the address' => ['', '?lang=id'],
]);

test('a public answer that does not follow the cookie does not vary by it', function () {
    $res = $this->get('/stats/github.json')->assertOk();
    expect($res->headers->get('Cache-Control'))->toContain('public');
    expect((string) $res->headers->get('Vary'))->not->toContain('Cookie');
});

test('a signed-in page stays no-store', function () {
    $res = $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk();
    expect($res->headers->get('Cache-Control'))->toContain('no-store');
});
