<?php

use App\Models\User;
use Hop\Ui\Locales;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/*
 * Path, i18n and error-page checks that came out of a link/button/HTML audit of the hub.
 * Each test pins one failure that a green suite did not catch.
 */

test('the marketing footer "Features" link does not use a path that only exists on the public host', function () {
    $html = $this->get('/privacy')->assertOk()->getContent();

    expect($html)->not->toContain('href="/apps"')
        ->and($html)->toContain('href="'.route('home').'#apps"');
});

test('footer language links keep the current query string (no bare ?lang= links)', function () {
    $html = $this->get('/changelog?app=PixelHop')->assertOk()->getContent();

    expect($html)->not->toContain('href="?lang=')
        ->and($html)->toContain('app=PixelHop&amp;lang=de');
});

test('the 404 page is the hub page, in the requested language, without LinkHop text', function () {
    $html = $this->get('/does-not-exist?lang=de')->assertNotFound()->getContent();

    expect($html)->toContain('<html lang="de"')
        ->and($html)->toContain(__('Page not found', [], 'de'))
        ->and($html)->not->toContain('LinkHop')
        ->and($html)->not->toContain('shortlink');
});

test('the 500 page does not promise that a team was notified, and follows the language', function () {
    app()->setLocale('ru');
    $html = view('errors.500', ['exception' => new RuntimeException('x')])->render();

    expect($html)->not->toContain('has been notified')
        ->and($html)->toContain('<html lang="ru"')
        ->and($html)->toContain(__('Something went wrong', [], 'ru'));
});

test('the 429 page does not talk about LinkHop', function () {
    app()->setLocale('de');
    $html = view('errors.429', ['exception' => new TooManyRequestsHttpException(30)])->render();

    expect($html)->not->toContain('keep LinkHop stable');
});

test('the home page still renders when the status tables cannot be read', function () {
    Schema::drop('service_checks');
    Schema::drop('announcements');

    $this->get('/')->assertOk();
});

test('the language cookie is host-only even when SESSION_DOMAIN is set to the hub host', function () {
    config(['session.domain' => 'hub.example.test']);

    $cookie = collect($this->get('/?lang=de')->headers->getCookies())->first(fn ($c) => $c->getName() === 'hop_lang');

    expect($cookie)->not->toBeNull()
        ->and($cookie->getDomain())->toBeNull();
});

test('the settings page has no duplicate element ids', function () {
    $this->actingAs(User::factory()->create());
    $html = $this->get('/settings')->assertOk()->getContent();
    preg_match_all('/\sid="([^"{]+)"/', $html, $m);
    $dupes = array_keys(array_filter(array_count_values($m[1]), fn ($n) => $n > 1));

    expect($dupes)->toBe([]);
});

test('messages set in bootstrap/app.php are catalog keys in every language, not literals', function () {
    expect(file_get_contents(base_path('bootstrap/app.php')))->not->toContain('Sesi habis');
    foreach (['Your session expired. Try again.', 'You have been signed out.'] as $key) {
        foreach (Locales::codes() as $code) {
            expect(json_decode(file_get_contents(base_path("lang/{$code}.json")), true))->toHaveKey($key);
        }
    }
});

test('literal __() texts in views are in the catalog, except the recorded baseline', function () {
    $en = json_decode(file_get_contents(base_path('lang/en.json')), true);
    $baseline = array_filter(array_map('trim', file(base_path('tests/Fixtures/i18n-missing-baseline.txt'))));
    $found = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('resources/views'), FilesystemIterator::SKIP_DOTS));
    foreach (array_merge(iterator_to_array($it), [new SplFileInfo(base_path('packages/ui/resources/views/components/account-menu.blade.php'))]) as $file) {
        if (! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }
        preg_match_all("/__\(\s*'((?:[^'\\\\]|\\\\.)*)'/s", file_get_contents($file->getPathname()), $m);
        foreach ($m[1] as $text) {
            $text = stripcslashes($text);
            if (! array_key_exists($text, $en) && ! str_contains($text, '$')) {
                $found[$text] = true;
            }
        }
    }
    $missing = array_keys($found);
    sort($missing);

    // New gaps fail; gaps that were closed must be removed from the baseline file.
    expect(array_values(array_diff($missing, $baseline)))->toBe([], 'new untranslated __() texts (add them to lang/*.json)')
        ->and(array_values(array_diff($baseline, $missing)))->toBe([], 'remove closed gaps from tests/Fixtures/i18n-missing-baseline.txt');
});

test('the settings page has as many closing div tags as opening ones', function () {
    $this->actingAs(User::factory()->create());
    $html = $this->get('/settings')->assertOk()->getContent();

    expect(preg_match_all('/<div\b/', $html))->toBe(preg_match_all('/<\/div>/', $html));
});

test('a recovery code is compared as a string, not as a number (0E... codes are all equal as numbers)', function () {
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_recovery_codes' => encrypt(json_encode(['0E00000001', 'ABCDEF0123'])),
        'two_factor_confirmed_at' => now(),
    ]);
    $this->actingAs($user)->withSession(['2fa_verified' => false]);

    $this->post('/two-factor-challenge', ['code' => '0E99999999'])->assertSessionHasErrors('code');
    expect(session('2fa_verified'))->toBeFalse();
});

test('every literal route() name used in app code and views is a registered route', function () {
    $missing = [];
    $roots = [base_path('app'), base_path('resources/views'), base_path('packages/ui/resources/views'), base_path('packages/ui/src')];
    foreach ($roots as $root) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }
            preg_match_all("/(?<![\\w>])(?:route|to_route)\(\s*'([a-z][\\w.-]*)'/", file_get_contents($file->getPathname()), $m);
            foreach ($m[1] as $name) {
                // apps.show only exists when the public and hub hosts differ (app_page_url() checks Route::has).
                if (! Route::has($name) && $name !== 'apps.show' && ! in_array($name, ['token', 'user'], true)) {
                    $missing[$name] = $file->getFilename();
                }
            }
        }
    }

    expect($missing)->toBe([]);
});

test('env() is only called from config files, so config:cache cannot silently drop a value', function () {
    $hits = [];
    foreach (['app', 'routes', 'resources/views', 'database', 'bootstrap'] as $dir) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir), FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (str_ends_with($file->getFilename(), '.php') && preg_match('/\benv\(/', file_get_contents($file->getPathname()))) {
                $hits[] = str_replace(base_path().'/', '', $file->getPathname());
            }
        }
    }

    expect($hits)->toBe([]);
});

test('the settings page follows a hash change on the open page', function () {
    $this->withoutVite()->actingAs(User::factory()->create())->withSession(['2fa_verified' => true])
        ->get('/settings')->assertOk()->assertSee('@hashchange.window', false);
});

test('signed-in pages carry no cookie banner (only essential cookies exist)', function () {
    $this->withoutVite()->actingAs(User::factory()->create())->withSession(['2fa_verified' => true])
        ->get('/settings')->assertOk()->assertDontSee('cookie_consent', false);
});

test('the goodbye page is in the language of the visitor and does not talk about LinkHop', function (string $locale) {
    $html = $this->withoutVite()->get('/goodbye?lang='.$locale)->assertOk()->getContent();

    expect($html)->toContain('<html lang="'.$locale.'"')
        ->and($html)->not->toContain('LinkHop')
        ->and($html)->toContain(e(__('Your account has been deleted', [], $locale)))
        ->and($html)->toContain(e(__('Thank you for using HOP. We hope to see you again someday.', [], $locale)))
        ->and($html)->toContain(e(__('Back to the home page', [], $locale)))
        ->and($html)->toContain(e(__('Create your HOP ID', [], $locale)));
    if ($locale !== 'en') {
        expect(__('Your account has been deleted', [], $locale))->not->toBe('Your account has been deleted')
            ->and($html)->not->toContain('This action cannot be undone');
    }
})->with(Locales::codes());
