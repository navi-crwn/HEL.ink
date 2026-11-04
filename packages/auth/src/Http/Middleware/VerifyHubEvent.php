<?php

namespace Hop\Auth\Http\Middleware;

use Closure;
use Hop\Auth\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * `hop.event`: lets through only a message signed by the hub with this app's
 * secret (HOP App Protocol v1, the same HMAC scheme and the same secret and
 * previous secret as the sign-in tickets):
 *
 *   X-HOP-Signature = base64url(HMAC-SHA256(secret,
 *       "hop1|helink|ts|nonce|POST|request-uri|sha256hex(body)"))
 *
 * The timestamp must be within `hop-auth.events.max_age` seconds and each
 * nonce works once. Every refusal is the same bare 401, so a caller learns
 * nothing about which check failed (or whether a secret is set at all).
 */
class VerifyHubEvent
{
    public function handle(Request $request, Closure $next): Response
    {
        return $this->verified($request) ? $next($request) : response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
    }

    private function verified(Request $request): bool
    {
        $secrets = Ticket::resolveSecrets();
        $hub = (string) config('hop-auth.events.hub_app', 'helink');
        $ts = (string) $request->header('X-HOP-Timestamp', '');
        $nonce = (string) $request->header('X-HOP-Nonce', '');
        $sig = (string) $request->header('X-HOP-Signature', '');
        $maxAge = (int) config('hop-auth.events.max_age', 120);
        if ($secrets === [] || $request->header('X-HOP-App') !== $hub || $sig === '' || strlen($nonce) < 16 || strlen($nonce) > 128
            || ! ctype_digit($ts) || abs(time() - (int) $ts) > $maxAge) {
            return false;
        }
        $base = implode('|', ['hop1', $hub, $ts, $nonce, $request->getMethod(), $request->getRequestUri(), hash('sha256', (string) $request->getContent())]);
        $valid = false;
        foreach ($secrets as $secret) {
            $valid = hash_equals(Ticket::b64(hash_hmac('sha256', $base, $secret, true)), $sig) || $valid;
        }

        // The nonce is spent only after the signature checked out, so strangers cannot fill the cache.
        return $valid && Cache::add('hop:nonce:'.$hub.':'.sha1($nonce), 1, $maxAge * 2);
    }
}
