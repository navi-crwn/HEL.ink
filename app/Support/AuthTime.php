<?php

namespace App\Support;

/**
 * When the person last proved who they are at the hub (password, Google, a new sign-up, or the second factor
 * when that was the last step), in Unix seconds, kept in the hub session. Restoring a "remember me" cookie and
 * handing the session on to an app (SSO) are not a new proof and never touch it. 0 means "not known".
 */
final class AuthTime
{
    public const KEY = 'auth_time';

    public static function stamp(): void
    {
        session()->put(self::KEY, time());
    }

    /**
     * A real sign-in (password, Google) that still has the second factor to go: the stamp is set when the code is
     * accepted, and only then. A session that was restored by a "remember me" cookie has no such mark, so a code
     * alone never gives a fresh stamp.
     */
    public static function awaitSecondFactor(): void
    {
        session()->put('auth_time_pending', true);
    }

    /** The second factor was accepted: stamp, but only when it follows a real sign-in of this session. */
    public static function stampAfterSecondFactor(): void
    {
        if (session()->pull('auth_time_pending', false) === true) {
            self::stamp();
        }
    }

    public static function get(): int
    {
        return max(0, (int) session(self::KEY, 0));
    }

    /**
     * The `max_age` an app asked for on GET /sso/{app}, in seconds, or null when it asked for nothing usable.
     * Only digits count (anything else is ignored, the app checks the signed `auth_time` itself), at most a day,
     * at least ten seconds so that the sign-in just done always passes. `prompt=login` is `max_age=60`.
     */
    public static function maxAge(mixed $maxAge, mixed $prompt): ?int
    {
        $age = null;
        if (is_string($maxAge) && preg_match('/^[0-9]+$/', $maxAge) === 1) {
            $age = max(10, min((int) $maxAge, 86400));
        }
        if ($prompt === 'login') {
            $age = $age === null ? 60 : min($age, 60);
        }

        return $age;
    }
}
