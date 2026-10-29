<?php

namespace Hop\Auth;

/**
 * Verifies a HOP ID sign-in ticket: base64url(json) "." base64url(HMAC-SHA256).
 * Checks the signature, the audience (this app), the expiry and the state
 * the app sent. Returns the payload (sub, email, username, roles, admin, …).
 */
final class Ticket
{
    /**
     * @param  string|list<string>|null  $secret
     * @return array<string, mixed>
     */
    public static function verify(string $ticket, string $expectedState, string|array|null $secret = null, ?string $audience = null): array
    {
        $candidates = self::resolveSecrets($secret);
        $audience ??= (string) config('hop-auth.app');
        if (empty($candidates)) {
            throw new TicketException('not_configured');
        }
        $parts = explode('.', $ticket);
        if (count($parts) !== 2) {
            throw new TicketException('malformed');
        }
        [$body, $sig] = $parts;
        $validSignature = false;
        foreach ($candidates as $candidate) {
            $expected = self::b64(hash_hmac('sha256', $body, $candidate, true));
            if (hash_equals($expected, $sig)) {
                $validSignature = true;
                break;
            }
        }
        if (! $validSignature) {
            throw new TicketException('bad_signature');
        }
        $payload = json_decode((string) self::unb64($body), true);
        if (! is_array($payload) || ! isset($payload['sub'], $payload['exp'], $payload['state'])) {
            throw new TicketException('malformed');
        }
        $leeway = (int) config('hop-auth.leeway', 30);
        if ((int) $payload['exp'] + $leeway < time() || (isset($payload['iat']) && (int) $payload['iat'] - $leeway > time())) {
            throw new TicketException('expired');
        }
        if (($payload['aud'] ?? $audience) !== $audience) {
            throw new TicketException('wrong_audience');
        }
        if (! hash_equals($expectedState, (string) $payload['state'])) {
            throw new TicketException('state_mismatch');
        }

        return $payload;
    }

    /**
     * @param  string|list<string>|null  $secret
     * @return list<string>
     */
    public static function resolveSecrets(string|array|null $secret = null): array
    {
        if (is_array($secret)) {
            $raw = $secret;
        } elseif ($secret !== null && $secret !== '') {
            $raw = [$secret];
        } else {
            $raw = [
                (string) config('hop-auth.secret'),
                (string) config('hop-auth.secret_prev'),
            ];
        }

        return array_values(array_filter($raw, fn ($s) => is_string($s) && strlen($s) >= 32));
    }

    public static function b64(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function unb64(string $s): string|false
    {
        return base64_decode(strtr($s, '-_', '+/'), true);
    }
}
