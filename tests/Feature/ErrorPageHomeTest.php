<?php

use App\Models\User;

/**
 * The way back on an error page: a signed-in member goes to the dashboard, a visitor to the public home
 * (the rule of the family's public pages, see MemberLinksTest).
 */
function errorPageBackHref(string $html, string $label): ?string
{
    return preg_match('#<a href="([^"]+)"[^>]*>(?:(?!</a>).)*?'.preg_quote($label, '#').'#s', $html, $m) === 1 ? html_entity_decode($m[1]) : null;
}

test('the 404 page leads a member back to the dashboard', function () {
    $html = $this->withoutVite()->actingAs(User::factory()->create())->withSession(['2fa_verified' => true])
        ->get('/does-not-exist')->assertNotFound()->getContent();

    expect(errorPageBackHref($html, 'Back to your dashboard'))->toBe(hub_url('dashboard'))
        ->and($html)->not->toContain('Back to the home page');
});

test('the 404 page leads a visitor back to the home page', function () {
    $html = $this->withoutVite()->get('/does-not-exist')->assertNotFound()->getContent();

    expect(errorPageBackHref($html, 'Back to the home page'))->toBe(url('/'))
        ->and($html)->not->toContain('Back to your dashboard');
});

test('the label of the way back is in the language of the page', function () {
    $html = $this->withoutVite()->actingAs(User::factory()->create())->withSession(['2fa_verified' => true])
        ->get('/does-not-exist?lang=de')->assertNotFound()->getContent();

    expect($html)->toContain('Zurück zum Dashboard');
});

test('the 500 page and the missing-link page follow the same rule', function () {
    foreach (['errors.500' => ['exception' => new RuntimeException('x')], 'errors.link-missing' => ['slug' => 'abc']] as $view => $data) {
        $guest = view($view, $data)->render();
        expect($guest)->toContain('Back to the home page')->and($guest)->not->toContain('Back to your dashboard');

        $this->actingAs(User::factory()->create());
        $member = view($view, $data)->render();
        expect($member)->toContain('Back to your dashboard')->and($member)->toContain('href="'.hub_url('dashboard').'"')
            ->and($member)->not->toContain('Back to the home page');
        auth()->logout();
    }
});

test('an error page still renders when the session cannot be read', function () {
    auth()->shouldUse('does-not-exist');   // an unknown guard throws
    [$url, $label] = hop_error_home();

    expect($url)->toBe(url('/'))->and($label)->toBe('Back to the home page');
});
