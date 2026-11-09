<?php

use App\Models\ApiToken;
use App\Models\User;
use Hop\Ui\Assets;
use Illuminate\Support\Facades\Cache;

/**
 * The shared HOP dialogs and reminder cards come from the asset manifest (ui_css, ui_js).
 * The hub loads them when the manifest has them and works without them when it does not,
 * so it does not matter which is deployed first.
 */
function manifestWithUi(): void
{
    $manifest = Assets::bundled();
    $manifest['assets']['ui_css'] = ['url' => 'https://hel.ink/hop-assets/v9/hop-ui.css', 'integrity' => 'sha384-cssHASH'];
    $manifest['assets']['ui_js'] = ['url' => 'https://hel.ink/hop-assets/v9/hop-ui.js', 'integrity' => 'sha384-jsHASH'];
    config(['hop-ui.assets.remote' => true]);
    Cache::put('hop-ui:assets', $manifest, 300);
}

function manifestWithoutUi(): void
{
    $manifest = Assets::bundled();
    unset($manifest['assets']['ui_css'], $manifest['assets']['ui_js']);
    config(['hop-ui.assets.remote' => true]);
    Cache::put('hop-ui:assets', $manifest, 300);
}

test('a manifest without the dialog assets renders no tags and the pages still work', function () {
    manifestWithoutUi();
    $this->get('/login')->assertOk()->assertDontSee('hop-ui.js', false)->assertDontSee('hop-ui.css', false);

    $this->actingAs(User::factory()->create());
    $this->get('/dashboard')->assertOk()->assertDontSee('hop-ui.js', false);
});

test('with the dialog assets in the manifest the dashboard and sign-in layouts load them with SRI and the nonce', function () {
    manifestWithUi();

    foreach (['/login', '/register'] as $page) {
        $html = $this->get($page)->assertOk()->getContent();
        expect($html)
            ->toContain('<link rel="stylesheet" href="https://hel.ink/hop-assets/v9/hop-ui.css" integrity="sha384-cssHASH" crossorigin="anonymous">')
            ->toMatch('#<script src="https://hel\.ink/hop-assets/v9/hop-ui\.js" integrity="sha384-jsHASH" crossorigin="anonymous" defer\s*(nonce="[^"]+")?\s*></script>#');
        // The same nonce as the page's own inline scripts, whenever the page has one.
        if (preg_match('#<script nonce="([^"]+)">#', $html, $inline)) {
            expect($html)->toMatch('#hop-ui\.js"[^>]*nonce="'.preg_quote($inline[1], '#').'"#');
        }
    }

    $this->actingAs(User::factory()->create());
    foreach (['/dashboard', '/settings'] as $page) {
        $html = $this->get($page)->assertOk()->getContent();
        expect(substr_count($html, 'hop-ui.js'))->toBe(1)->and(substr_count($html, 'hop-ui.css'))->toBe(1);
    }
});

test('a delete asks as a delete: red button, the name of what goes, and the text stays for the browser dialog', function () {
    $user = User::factory()->create();
    ApiToken::generate(userId: $user->id, name: 'ShareX desktop');
    $this->actingAs($user);

    $html = $this->get('/settings')->assertOk()->getContent();
    preg_match('#<form method="POST" action="[^"]*/api-tokens/\d+"[^>]*>#', $html, $form);

    expect($form[0] ?? '')
        ->toContain('data-confirm="Revoke the token “ShareX desktop”?')
        ->toContain('data-danger')
        ->toContain('data-subject="ShareX desktop"')
        ->toContain('data-confirm-ok="Revoke"');
    // No native dialog is called from the page itself any more.
    expect($html)->not->toMatch('/[^.\w]alert\(/')->not->toMatch('/window\.(confirm|prompt)\(/');
});
