<?php

use App\Support\FamilyStory;
use Hop\Ui\Locales;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;

/** The public host (hel.ink) as search engines see it: what each page says about itself and where its buttons lead. */
beforeEach(function () {
    $this->withoutVite();
    config()->set('hop.public_host', 'hel.test');
    config()->set('hop.hub_host', 'hub.hel.test');
    config()->set('hop.hub_url', 'https://hub.hel.test');
    config()->set('hop.public_url', 'https://hel.test');
    $this->app['router']->setRoutes(new RouteCollection);
    Route::middleware('web')->group(base_path('routes/web.php'));
    $this->app['router']->getRoutes()->refreshNameLookups();
});

function hubPageHtml($test, string $path, ?string $lang = null): string
{
    return $test->get('https://hel.test'.$path.($lang ? '?lang='.$lang : ''))->assertOk()->getContent();
}

test('every public page has its own meta description in every language, of a sensible length', function () {
    $paths = ['', 'about', 'products', 'how-it-works', 'security', 'open-source', 'signup', 'plans', 'faq', 'help', 'status', 'changelog', 'announcements', 'contact', 'privacy', 'terms'];
    foreach (FamilyStory::APPS as $app) {
        $paths[] = 'apps/'.$app;
    }
    foreach (Locales::codes() as $lang) {
        // Chinese and Japanese say the same in far fewer characters.
        $min = in_array($lang, ['zh-CN', 'ja'], true) ? 35 : 70;
        $seen = [];
        foreach ($paths as $path) {
            $html = hubPageHtml($this, '/'.$path, $lang);
            preg_match_all('/<meta name="description" content="([^"]*)"/', $html, $found);
            expect($found[1])->toHaveCount(1, "{$lang} /{$path}: one description");
            $text = html_entity_decode($found[1][0], ENT_QUOTES);
            $length = mb_strlen($text);
            expect($length)->toBeGreaterThanOrEqual($min, "{$lang} /{$path} too short ({$length}): {$text}")
                ->and($length)->toBeLessThanOrEqual(160, "{$lang} /{$path} too long ({$length}): {$text}");
            expect($seen)->not->toHaveKey($text, "{$lang} /{$path} repeats the description of ".($seen[$text] ?? ''));
            $seen[$text] = $path;
        }
    }
});

test('the app pages only link to pages that exist', function () {
    expect(hubPageHtml($this, '/apps/payhop'))->not->toContain('pay.hel.ink/features');
    expect(hubPageHtml($this, '/apps/filehop'))->not->toContain('file.hel.ink/features');
    foreach (['linkhop', 'biohop', 'pixelhop', 'notehop'] as $app) {
        expect(hubPageHtml($this, '/apps/'.$app))->toContain('/features"');
    }
});

test('the home page links to each app say which app they lead to', function () {
    $html = hubPageHtml($this, '/');
    foreach (['LinkHop', 'BioHop', 'PixelHop', 'NoteHop', 'FileHop', 'PayHop'] as $name) {
        expect($html)->toContain('Learn more about '.$name);
    }
    expect(hubPageHtml($this, '/', 'de'))->toContain('Mehr über LinkHop erfahren');
});
