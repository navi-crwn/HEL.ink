<?php

use Hop\Auth\Http\Controllers\SsoController;
use Hop\Auth\Ticket;
use Hop\Auth\TicketException;

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

test('isHopUrl only accepts https urls inside the hop family', function () {
    expect(SsoController::isHopUrl('https://hel.ink/auth/sso/logout'))->toBeTrue();
    expect(SsoController::isHopUrl('https://link.hel.ink/auth/sso/logout'))->toBeTrue();
    expect(SsoController::isHopUrl('https://HUB.HEL.INK/logout'))->toBeTrue();

    expect(SsoController::isHopUrl('javascript://hel.ink/%0aalert(1)'))->toBeFalse();
    expect(SsoController::isHopUrl('http://hel.ink/auth/sso/logout'))->toBeFalse();
    expect(SsoController::isHopUrl('https://evil.hel.ink.attacker.com/auth/sso/logout'))->toBeFalse();
    expect(SsoController::isHopUrl('https://attacker.com/hel.ink'))->toBeFalse();
    expect(SsoController::isHopUrl(''))->toBeFalse();
});

test('safePath only accepts clean relative paths', function () {
    expect(SsoController::safePath('/dashboard'))->toBe('/dashboard');
    expect(SsoController::safePath('/links?page=2&q=a'))->toBe('/links?page=2&q=a');

    expect(SsoController::safePath('//evil'))->toBe('');
    expect(SsoController::safePath('/\\evil'))->toBe('');
    expect(SsoController::safePath('https://evil.com'))->toBe('');
    expect(SsoController::safePath(''))->toBe('');
});
