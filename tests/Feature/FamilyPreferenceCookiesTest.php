<?php

use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Http\Request;

/*
 * Family cookies (train 4): hop_lang carries Domain=.hel.ink on hel.ink and its subdomains (both hub hosts),
 * stays host-only elsewhere, and the old host-only cookie is cleaned up once per browser and host.
 */

function langCookies($response): array
{
    $out = [];
    foreach ($response->headers->getCookies() as $c) {
        if (! str_starts_with($c->getName(), 'hop_')) {
            continue; // the session and CSRF cookies are not ours
        }
        $out[] = ['name' => $c->getName(), 'value' => $c->getValue(), 'domain' => $c->getDomain(), 'secure' => $c->isSecure(), 'httpOnly' => $c->isHttpOnly(), 'expires' => $c->getExpiresTime(), 'sameSite' => $c->getSameSite()];
    }

    return $out;
}

function pickCookie(array $cookies, string $name, bool $domain): ?array
{
    foreach ($cookies as $c) {
        if ($c['name'] === $name && ($c['domain'] !== null) === $domain) {
            return $c;
        }
    }

    return null;
}

test('the domain rule: hel.ink and its subdomains share, everything else is host-only', function () {
    foreach (['hel.ink', 'hub.hel.ink', 'link.hel.ink', 'HEL.INK'] as $host) {
        expect(SetLocale::sharedDomain(Request::create("https://{$host}/")))->toBe('.hel.ink');
    }
    foreach (['localhost', 'hel.ink.example.com', 'nothel.ink', 'example.test'] as $host) {
        expect(SetLocale::sharedDomain(Request::create("http://{$host}/")))->toBeNull();
    }
});

test('a language picked on the public host or the hub host is written for .hel.ink, plain and readable by scripts', function () {
    foreach (['hel.ink', 'hub.hel.ink'] as $host) {
        $res = $this->withoutVite()->get("https://{$host}/about?lang=de");
        $c = pickCookie(langCookies($res), 'hop_lang', true);
        expect($c)->not->toBeNull()
            ->and($c['value'])->toBe('de')->and($c['domain'])->toBe('.hel.ink')
            ->and($c['secure'])->toBeTrue()->and($c['httpOnly'])->toBeFalse()->and($c['sameSite'])->toBe('lax')
            ->and($c['expires'])->toBeGreaterThan(time() + 360 * 86400)->toBeLessThanOrEqual(time() + 366 * 86400);
    }
});

test('on any other host the language cookie is host-only and nothing is migrated', function () {
    $res = $this->withoutVite()->withUnencryptedCookie('hop_lang', 'de')->get('http://localhost/about?lang=id');
    $cookies = langCookies($res);
    expect(pickCookie($cookies, 'hop_lang', true))->toBeNull()
        ->and(pickCookie($cookies, 'hop_lang', false)['value'])->toBe('id')
        ->and(pickCookie($cookies, 'hop_pref', false))->toBeNull();
    $res = $this->withoutVite()->withUnencryptedCookie('hop_lang', 'de')->get('http://localhost/about');
    expect(array_column(langCookies($res), 'name'))->not->toContain('hop_lang')->not->toContain('hop_pref')
        ->and($res->getContent())->toContain('lang="de"');
});

test('clean-up, no cookie at all: the old host-only cookie is expired and the marker is set', function () {
    $cookies = langCookies($this->withoutVite()->get('https://hel.ink/about'));
    $old = pickCookie($cookies, 'hop_lang', false);
    expect($old['value'])->toBe('')->and($old['expires'])->toBeLessThan(time())
        ->and(pickCookie($cookies, 'hop_lang', true))->toBeNull();
    $marker = pickCookie($cookies, 'hop_pref', false);
    expect($marker['value'])->toBe('2')->and($marker['domain'])->toBeNull()->and($marker['expires'])->toBeGreaterThan(time() + 360 * 86400);
});

test('clean-up, old cookie only: the value moves to the .hel.ink cookie and the page uses it', function () {
    $res = $this->withoutVite()->withUnencryptedCookie('hop_lang', 'zh')->get('https://hub.hel.ink/about');
    $cookies = langCookies($res);
    expect(pickCookie($cookies, 'hop_lang', false)['expires'])->toBeLessThan(time())
        ->and(pickCookie($cookies, 'hop_lang', true)['value'])->toBe('zh-CN')
        ->and(pickCookie($cookies, 'hop_lang', true)['domain'])->toBe('.hel.ink')
        ->and(pickCookie($cookies, 'hop_pref', false)['value'])->toBe('2');
    expect($res->getContent())->toContain('lang="zh-CN"');
    // A retired language is not carried over.
    $cookies = langCookies($this->withoutVite()->withUnencryptedCookie('hop_lang', 'ar')->get('https://hel.ink/about'));
    expect(pickCookie($cookies, 'hop_lang', true))->toBeNull()->and(pickCookie($cookies, 'hop_pref', false)['value'])->toBe('2');
});

test('clean-up, marker present: nothing is touched', function () {
    $res = $this->withoutVite()->withUnencryptedCookies(['hop_lang' => 'fr', 'hop_pref' => '2'])->get('https://hel.ink/about');
    expect(langCookies($res))->toBe([])->and($res->getContent())->toContain('lang="fr"');
    // ?lang= still writes the domain cookie, and only that.
    $cookies = langCookies($this->withoutVite()->withUnencryptedCookies(['hop_lang' => 'fr', 'hop_pref' => '2'])->get('https://hel.ink/about?lang=ru'));
    expect(array_column($cookies, 'name'))->toBe(['hop_lang'])->and($cookies[0]['value'])->toBe('ru')->and($cookies[0]['domain'])->toBe('.hel.ink');
});

test('a choice made with ?lang= during the clean-up wins over the old cookie', function () {
    $cookies = langCookies($this->withoutVite()->withUnencryptedCookie('hop_lang', 'de')->get('https://hel.ink/about?lang=es'));
    $domainValues = array_map(fn ($c) => $c['value'], array_filter($cookies, fn ($c) => $c['name'] === 'hop_lang' && $c['domain'] !== null));
    expect(array_values($domainValues))->toBe(['es'])->and(pickCookie($cookies, 'hop_lang', false)['expires'])->toBeLessThan(time());
});

test('the theme writer is on the marketing (home layout), sign-in, dashboard and settings pages', function () {
    foreach (['/about', '/changelog', '/login', '/register', '/goodbye'] as $url) {
        expect($this->withoutVite()->get($url)->getContent())->toContain('hop_theme=');
    }
    $user = User::factory()->create();
    foreach (['/dashboard', '/settings'] as $path) {
        expect($this->withoutVite()->actingAs($user)->withSession(['2fa_verified' => true])->get($path)->getContent())->toContain('hop_theme=');
    }
});
