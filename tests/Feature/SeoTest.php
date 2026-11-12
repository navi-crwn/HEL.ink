<?php

use App\Models\Announcement;
use App\Models\User;
use App\Support\FamilyStory;
use Hop\Ui\Locales;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['hop.public_url' => 'https://hel.ink']);
    Http::fake();
});

function seoXpath(string $html): DOMXPath
{
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);

    return new DOMXPath($dom);
}

function seoCanonicals(string $html): array
{
    $out = [];
    foreach (seoXpath($html)->query('//head/link[@rel="canonical"]') as $link) {
        $out[] = $link->getAttribute('href');
    }

    return $out;
}

function seoSitemap(string $xml): array
{
    $doc = simplexml_load_string($xml);
    expect($doc)->not->toBeFalse();
    $out = [];
    foreach ($doc->url as $url) {
        $alternates = [];
        foreach ($url->children('http://www.w3.org/1999/xhtml')->link as $link) {
            $alternates[(string) $link->attributes()->hreflang] = (string) $link->attributes()->href;
        }
        $out[(string) $url->loc] = $alternates;
    }
    expect(count($out))->toBe(count($doc->url));

    return $out;
}

test('the home page names one canonical URL on the public host, per language and without other query strings', function () {
    expect(seoCanonicals($this->get('/')->assertOk()->getContent()))->toBe(['https://hel.ink/']);
    expect(seoCanonicals($this->get('/?utm_source=x&page=2')->getContent()))->toBe(['https://hel.ink/']);
    expect(seoCanonicals($this->get('/?lang=de&utm_source=x')->getContent()))->toBe(['https://hel.ink/?lang=de']);
    expect(seoCanonicals($this->get('/?lang=pt')->getContent()))->toBe(['https://hel.ink/?lang=pt-BR']);
    // English is the default language and a retired language is English: both are the plain URL.
    expect(seoCanonicals($this->get('/?lang=en')->getContent()))->toBe(['https://hel.ink/']);
    expect(seoCanonicals($this->get('/?lang=ar')->getContent()))->toBe(['https://hel.ink/']);
});

test('every marketing page has a canonical URL, og:url, an absolute og:image and a twitter card', function () {
    foreach (['about', 'products', 'how-it-works', 'security', 'open-source', 'signup', 'plans', 'faq', 'help', 'status', 'changelog', 'announcements', 'contact', 'privacy', 'terms'] as $page) {
        $html = $this->get('/'.$page.'?ref=x')->assertOk()->getContent();
        $xpath = seoXpath($html);
        expect(seoCanonicals($html))->toBe(['https://hel.ink/'.$page]);
        expect($xpath->query('//head/meta[@property="og:url"][@content="https://hel.ink/'.$page.'"]')->length)->toBe(1);
        expect($xpath->query('//head/meta[@name="twitter:card"]')->length)->toBe(1);
        expect($xpath->query('//head/meta[@property="og:image"]')->item(0)->getAttribute('content'))->toMatch('#^https?://[^/]+/brand/.+\.png$#');
        expect($xpath->query('//head/meta[@name="robots"]')->length)->toBe(0);
    }
});

test('the language alternates in the head are the nine languages and x-default, each its own canonical', function () {
    foreach (['/' => 'https://hel.ink/', '/about' => 'https://hel.ink/about'] as $path => $base) {
        $alternates = [];
        foreach (seoXpath($this->get($path)->getContent())->query('//head/link[@rel="alternate"][@hreflang]') as $link) {
            $alternates[$link->getAttribute('hreflang')] = $link->getAttribute('href');
        }
        expect(array_keys($alternates))->toBe([...Locales::codes(), 'x-default']);
        expect($alternates)->not->toHaveKey('ar');
        expect($alternates['x-default'])->toBe($base)->and($alternates['en'])->toBe($base);
        foreach (Locales::codes() as $code) {
            expect($code)->toMatch('/^[a-z]{2}(-[A-Z]{2})?$/');
            $url = $alternates[$code];
            $html = $this->get($path.(parse_url($url, PHP_URL_QUERY) ? '?'.parse_url($url, PHP_URL_QUERY) : ''))->assertOk()->getContent();
            expect($html)->toContain('<html lang="'.$code.'"');
            expect(seoCanonicals($html))->toBe([$url]);
        }
    }
});

test('the home page carries JSON-LD for the organization, the site and the six apps', function () {
    $html = $this->get('/')->assertOk()->getContent();
    $blocks = seoXpath($html)->query('//head/script[@type="application/ld+json"]');
    expect($blocks->length)->toBe(1);
    $data = json_decode($blocks->item(0)->textContent, true, 32, JSON_THROW_ON_ERROR);
    expect($data['@context'])->toBe('https://schema.org');
    $byType = collect($data['@graph'])->keyBy('@type');

    expect($byType['Organization'])->toMatchArray(['name' => 'HEL.ink', 'url' => 'https://hel.ink/']);
    expect($byType['Organization']['logo'])->toMatch('#^https?://[^/]+/brand/[a-f0-9]{8}/logo\.png$#');
    expect($byType['Organization']['sameAs'])->toBe([config('hop.github.org_url')]);
    // The same account the footer's repository link belongs to.
    expect($html)->toContain('href="'.config('hop.github.org_url').'/');

    expect($byType['WebSite'])->toMatchArray(['name' => 'HEL.ink', 'url' => 'https://hel.ink/', 'inLanguage' => Locales::codes()]);

    $items = collect($byType['ItemList']['itemListElement']);
    expect($items->pluck('position')->all())->toBe(range(1, $items->count()));
    expect($items->pluck('name', 'url')->all())->toMatchArray([
        'https://link.hel.ink' => 'LinkHop',
        'https://bio.hel.ink' => 'BioHop',
        'https://pixel.hel.ink' => 'PixelHop',
        'https://note.hel.ink' => 'NoteHop',
        'https://file.hel.ink' => 'FileHop',
        'https://pay.hel.ink' => 'PayHop',
    ]);
    // Not a second list: the block follows the registry the page itself is built from.
    config(['hop.apps.payhop.enabled' => false]);
    $again = seoXpath($this->get('/')->getContent())->query('//head/script[@type="application/ld+json"]')->item(0)->textContent;
    expect($again)->toContain('https://file.hel.ink')->not->toContain('https://pay.hel.ink');
});

test('account pages ask not to be indexed and the public home does not', function () {
    foreach (['/login', '/register', '/forgot-password', '/reset-password/some-token'] as $path) {
        $xpath = seoXpath($this->get($path)->assertOk()->getContent());
        expect($xpath->query('//head/meta[@name="robots"][@content="noindex, nofollow"]')->length)->toBe(1, $path);
        expect($xpath->query('//head/link[@rel="canonical"]')->length)->toBe(0, $path);
    }

    $admin = User::factory()->admin()->create();
    foreach (['/dashboard', '/settings', '/admin/users'] as $path) {
        $html = $this->actingAs($admin)->withSession(['2fa_verified' => true])->get($path)->assertOk()->getContent();
        expect(seoXpath($html)->query('//head/meta[@name="robots"][@content="noindex, nofollow"]')->length)->toBe(1, $path);
    }

    auth()->logout();
    $home = $this->get('/')->assertOk();
    expect($home->headers->has('X-Robots-Tag'))->toBeFalse();
    expect($home->getContent())->not->toContain('noindex');
});

test('the sitemap lists every public page once on the public host with its language alternates', function () {
    $live = Announcement::create(['title' => 'Live news', 'body' => 'Text', 'level' => 'info', 'published_at' => now()->subDay()]);
    $draft = Announcement::create(['title' => 'Draft news', 'body' => 'Text', 'level' => 'info', 'published_at' => null]);
    // Two hosts, as in production: the product pages exist on the public host only.
    config(['hop.public_host' => 'hel.ink', 'hop.hub_host' => 'hub.hel.ink']);

    $urls = seoSitemap($this->get('/sitemap.xml')->assertOk()->getContent());
    $expected = ['https://hel.ink/', 'https://hel.ink/announcements/'.$live->getRouteKey()];
    foreach (['about', 'products', 'how-it-works', 'security', 'open-source', 'signup', 'plans', 'faq', 'help', 'status', 'changelog', 'announcements', 'contact', 'privacy', 'terms'] as $page) {
        $expected[] = 'https://hel.ink/'.$page;
    }
    foreach (FamilyStory::APPS as $app) {
        $expected[] = 'https://hel.ink/apps/'.$app;
    }
    expect(array_keys($urls))->toEqualCanonicalizing($expected);
    expect(array_keys($urls))->not->toContain('https://hel.ink/announcements/'.$draft->getRouteKey());

    foreach ($urls as $loc => $alternates) {
        expect($loc)->not->toContain('hub.')->not->toContain('?')->not->toMatch('#/(login|register|dashboard|admin|settings|profile)#');
        expect(array_keys($alternates))->toBe([...Locales::codes(), 'x-default']);
        expect($alternates['x-default'])->toBe($loc)->and($alternates['en'])->toBe($loc)->and($alternates['zh-CN'])->toBe($loc.'?lang=zh-CN');
    }
});

test('every URL in the sitemap answers 200', function () {
    Announcement::create(['title' => 'Live news', 'body' => 'Text', 'level' => 'info', 'published_at' => now()->subDay()]);

    foreach (array_keys(seoSitemap($this->get('/sitemap.xml')->assertOk()->getContent())) as $loc) {
        $this->get(parse_url($loc, PHP_URL_PATH))->assertOk();
    }
});
