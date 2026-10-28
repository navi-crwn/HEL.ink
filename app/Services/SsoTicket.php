<?php

namespace App\Services;

use App\Models\User;
use App\Support\HopApp;

/**
 * Sign-in tickets for the HOP apps, the format PixelHop already verifies:
 * base64url(json payload) "." base64url(HMAC-SHA256(secret, first part)),
 * valid for two minutes, bound to the app (aud) and to the state the app
 * sent. Version 2 carries the HOP profile; the v1 fields are unchanged.
 */
final class SsoTicket
{
    public static function issue(User $user, HopApp $app, string $state): string
    {
        $now = time();
        $payload = $user->hopProfile() + [
            'aud' => $app->slug,
            'admin' => $user->isAdminOf($app->slug),
            'state' => $state,
            'iat' => $now,
            'exp' => $now + (int) config('hop.ticket_ttl', 120),
            'jti' => bin2hex(random_bytes(16)),
            'v' => 2,
        ];
        $body = self::b64(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $body.'.'.self::b64(hash_hmac('sha256', $body, (string) $app->secret, true));
    }

    public static function b64(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
