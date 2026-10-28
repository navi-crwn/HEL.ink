<?php

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
