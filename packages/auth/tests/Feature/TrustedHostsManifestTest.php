<?php

use Hop\Auth\Http\Controllers\SsoController;

/*
 * The default `trusted_hosts` must hold every host of the HOP family manifest, so the chained logout can pass on to
 * any app. tests/fixtures/hop-apps.json lists slug/url/account_url of the manifest (hop-assets apps.json); when the
 * manifest changes, replace its `apps` with the new ones and this test says which host is missing.
 */

function familyHosts(): array
{
    $manifest = json_decode((string) file_get_contents(__DIR__.'/../fixtures/hop-apps.json'), true);
    $hosts = [];
    foreach ($manifest['apps'] as $app) {
        foreach (['url', 'account_url'] as $key) {
            if (! empty($app[$key])) {
                $hosts[$app['slug'].' '.$key] = strtolower((string) parse_url($app[$key], PHP_URL_HOST));
            }
        }
    }

    return $hosts;
}

test('the fixture lists the family, PayHop included', function () {
    expect(familyHosts())->toHaveKey('payhop url')->and(familyHosts()['payhop url'])->toBe('pay.hel.ink');
    expect(count(familyHosts()))->toBeGreaterThanOrEqual(7);
});

test('the default trusted_hosts hold every host of the manifest', function () {
    $default = (require __DIR__.'/../../config/hop-auth.php')['trusted_hosts'];
    // Only the list itself: no hub, app or shared-config hosts mixed in.
    config()->set('hop-auth.trusted_hosts', $default);
    config()->set('hop-auth.hub_url', '');
    config()->set('app.url', '');
    config()->set('hop.public_url', '');
    config()->set('hop.hub_url', '');
    config()->set('hop.apps', []);

    $trusted = SsoController::trustedHosts();
    $expected = array_values(array_unique(familyHosts()));
    sort($expected);
    sort($trusted);

    // The list may hold more (www.hel.ink); it may not miss a manifest host.
    expect(array_values(array_diff($expected, $trusted)))->toBe([], 'trusted_hosts is missing a manifest host');
    foreach ($expected as $host) {
        expect(SsoController::isHopUrl("https://{$host}/auth/sso/logout"))->toBeTrue($host);
    }
});
