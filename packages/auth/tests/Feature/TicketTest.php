<?php

use Hop\Auth\Http\Controllers\SsoController;
use Hop\Auth\Ticket;
use Hop\Auth\TicketException;
use Illuminate\Http\Request;

function makeTicket(array $payload, string $secret): string
{
    $body = Ticket::b64(json_encode($payload));

    return $body.'.'.Ticket::b64(hash_hmac('sha256', $body, $secret, true));
}

beforeEach(function () {
    config()->set('hop-auth.secret', 'consumer-secret-that-is-long-enough-123456');
    config()->set('hop-auth.app', 'linkhop');
    config()->set('hop-auth.hub_url', 'https://hub.hel.ink');
});

test('a valid ticket returns its payload', function () {
    $t = makeTicket(['sub' => '7', 'aud' => 'linkhop', 'state' => 'st', 'iat' => time(), 'exp' => time() + 120, 'v' => 2], 'consumer-secret-that-is-long-enough-123456');
    expect(Ticket::verify($t, 'st')['sub'])->toBe('7');
});

test('bad signature, wrong audience, expiry and state mismatch are refused', function () {
    $secret = 'consumer-secret-that-is-long-enough-123456';
    $base = ['sub' => '7', 'aud' => 'linkhop', 'state' => 'st', 'iat' => time(), 'exp' => time() + 120];
    expect(fn () => Ticket::verify(makeTicket($base, 'other-secret-that-is-long-enough-00000000'), 'st'))->toThrow(TicketException::class, 'bad_signature');
    expect(fn () => Ticket::verify(makeTicket(['aud' => 'biohop'] + $base, $secret), 'st'))->toThrow(TicketException::class, 'wrong_audience');
    expect(fn () => Ticket::verify(makeTicket(['exp' => time() - 600] + $base, $secret), 'st'))->toThrow(TicketException::class, 'expired');
    expect(fn () => Ticket::verify(makeTicket($base, $secret), 'other'))->toThrow(TicketException::class, 'state_mismatch');
    expect(fn () => Ticket::verify('garbage', 'st'))->toThrow(TicketException::class, 'malformed');
});

test('isHopUrl only accepts https urls on a trusted HOP host', function () {
    foreach (['hel.ink', 'www.hel.ink', 'hub.hel.ink', 'link.hel.ink', 'bio.hel.ink', 'note.hel.ink', 'pixel.hel.ink', 'file.hel.ink', 'pay.hel.ink'] as $host) {
        expect(SsoController::isHopUrl("https://{$host}/auth/sso/logout?next=https%3A%2F%2Fhel.ink%2F"))->toBeTrue($host);
    }
    expect(SsoController::isHopUrl('https://HUB.HEL.INK/logout'))->toBeTrue();
    expect(SsoController::isHopUrl('https://hel.ink:443/auth/sso/logout'))->toBeTrue();

    expect(SsoController::isHopUrl('javascript://hel.ink/%0aalert(1)'))->toBeFalse();
    expect(SsoController::isHopUrl('http://hel.ink/auth/sso/logout'))->toBeFalse();
    expect(SsoController::isHopUrl('https://evil.hel.ink.attacker.com/auth/sso/logout'))->toBeFalse();
    expect(SsoController::isHopUrl('https://attacker.com/hel.ink'))->toBeFalse();
    expect(SsoController::isHopUrl(''))->toBeFalse();
});

test('isHopUrl no longer trusts every subdomain of the family domain', function () {
    expect(SsoController::isHopUrl('https://evil.hel.ink/'))->toBeFalse();
    expect(SsoController::isHopUrl('https://anything.hel.ink/auth/sso/logout'))->toBeFalse();
    expect(SsoController::isHopUrl('https://a.b.hel.ink/'))->toBeFalse();
    expect(SsoController::isHopUrl('https://hel.ink.evil.com/'))->toBeFalse();
    expect(SsoController::isHopUrl('https://evilhel.ink/'))->toBeFalse();
    expect(SsoController::isHopUrl('https://hel.ink./'))->toBeFalse();
});

test('isHopUrl refuses userinfo, scheme-relative, backslash and whitespace tricks', function () {
    expect(SsoController::isHopUrl('https://hel.ink@evil.com/'))->toBeFalse();
    expect(SsoController::isHopUrl('https://hel.ink:pass@evil.com/'))->toBeFalse();
    expect(SsoController::isHopUrl('https://evil.com@hel.ink/'))->toBeFalse();
    expect(SsoController::isHopUrl('//hel.ink/auth/sso/logout'))->toBeFalse();
    expect(SsoController::isHopUrl('//evil.com/'))->toBeFalse();
    expect(SsoController::isHopUrl('/\\evil.com'))->toBeFalse();
    expect(SsoController::isHopUrl('https://evil.com\\@hel.ink/'))->toBeFalse();
    expect(SsoController::isHopUrl('https://hel.ink\\.evil.com/'))->toBeFalse();
    expect(SsoController::isHopUrl("https://hel.ink/\n@evil.com"))->toBeFalse();
    expect(SsoController::isHopUrl(' https://hel.ink/'))->toBeFalse();
    expect(SsoController::isHopUrl("https://hel.ink\t.evil.com/"))->toBeFalse();
    expect(SsoController::isHopUrl('/auth/sso/logout'))->toBeFalse();
    expect(SsoController::isHopUrl('hel.ink'))->toBeFalse();
    expect(SsoController::isHopUrl('https:hel.ink'))->toBeFalse();
});

test('isHopUrl follows the configured hosts, the hub and the shared app urls', function () {
    config()->set('hop-auth.hub_url', 'https://hub.example.org');
    config()->set('hop-auth.trusted_hosts', ['links.example.org', 'https://bio.example.org/', 'Notes.Example.Org:8443']);
    config()->set('app.url', 'https://app.example.org');
    config()->set('hop.public_url', 'http://localhost');
    config()->set('hop.hub_url', 'http://localhost');
    config()->set('hop.apps', ['pixelhop' => ['url' => 'https://pix.example.org'], 'empty' => ['url' => '']]);

    foreach (['hub', 'links', 'bio', 'notes', 'app', 'pix'] as $label) {
        expect(SsoController::isHopUrl("https://{$label}.example.org/auth/sso/logout"))->toBeTrue($label);
    }
    expect(SsoController::isHopUrl('https://evil.example.org/'))->toBeFalse();
    expect(SsoController::isHopUrl('https://example.org/'))->toBeFalse();
    // Only the hosts in the list: an install that replaces it no longer trusts hel.ink.
    expect(SsoController::isHopUrl('https://hel.ink/'))->toBeFalse();
});

test('isHopUrl allows plain http only for a trusted local host', function () {
    config()->set('hop-auth.hub_url', 'http://localhost:8000');
    config()->set('hop-auth.trusted_hosts', ['localhost', '127.0.0.1', '[::1]', 'hub.example.org']);

    expect(SsoController::isHopUrl('http://localhost:8000/auth/sso/logout'))->toBeTrue();
    expect(SsoController::isHopUrl('http://127.0.0.1/auth/sso/logout'))->toBeTrue();
    expect(SsoController::isHopUrl('http://[::1]:8000/auth/sso/logout'))->toBeTrue();
    expect(SsoController::isHopUrl('https://localhost/auth/sso/logout'))->toBeTrue();
    expect(SsoController::isHopUrl('http://hub.example.org/'))->toBeFalse();
    expect(SsoController::isHopUrl('ftp://localhost/'))->toBeFalse();
    expect(SsoController::isHopUrl('http://localhost.evil.com/'))->toBeFalse();
});

test('the logout action only follows next to a trusted host', function () {
    $logout = function (?string $next) {
        $request = Request::create('/auth/sso/logout', 'GET', $next === null ? [] : ['next' => $next]);
        $request->setLaravelSession(app('session')->driver('array'));

        return (new SsoController)->logout($request)->getTargetUrl();
    };

    expect($logout('https://bio.hel.ink/auth/sso/logout?next=https%3A%2F%2Fhel.ink%2F'))->toBe('https://bio.hel.ink/auth/sso/logout?next=https%3A%2F%2Fhel.ink%2F');
    $home = $logout(null);
    expect($logout('https://evil.hel.ink/'))->toBe($home);
    expect($logout('https://hel.ink@evil.com/'))->toBe($home);
    expect($logout('//evil.com/'))->toBe($home);
});

test('safePath only accepts clean relative paths', function () {
    expect(SsoController::safePath('/dashboard'))->toBe('/dashboard');
    expect(SsoController::safePath('/links?page=2&q=a'))->toBe('/links?page=2&q=a');

    expect(SsoController::safePath('//evil'))->toBe('');
    expect(SsoController::safePath('/\\evil'))->toBe('');
    expect(SsoController::safePath('https://evil.com'))->toBe('');
    expect(SsoController::safePath(''))->toBe('');
});

test('ticket verification supports dual secret rotation with candidate secrets or secret_prev', function () {
    $currentSecret = 'new-primary-secret-that-is-long-enough-1234';
    $previousSecret = 'old-previous-secret-that-is-long-enough-5678';
    $base = ['sub' => '42', 'aud' => 'linkhop', 'state' => 'st-rot', 'iat' => time(), 'exp' => time() + 120];

    // Configure primary and previous secrets
    config()->set('hop-auth.secret', $currentSecret);
    config()->set('hop-auth.secret_prev', $previousSecret);

    // Ticket signed with current secret is valid
    $tCurrent = makeTicket($base, $currentSecret);
    expect(Ticket::verify($tCurrent, 'st-rot')['sub'])->toBe('42');

    // Ticket signed with previous secret during transition is also valid
    $tPrev = makeTicket($base, $previousSecret);
    expect(Ticket::verify($tPrev, 'st-rot')['sub'])->toBe('42');

    // Ticket signed with an unknown secret is refused
    $tUnknown = makeTicket($base, 'unknown-secret-that-is-long-enough-9999');
    expect(fn () => Ticket::verify($tUnknown, 'st-rot'))->toThrow(TicketException::class, 'bad_signature');

    // Passing array of candidate secrets explicitly to verify() works
    expect(Ticket::verify($tPrev, 'st-rot', [$currentSecret, $previousSecret])['sub'])->toBe('42');
});
