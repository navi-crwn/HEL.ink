<?php

namespace App\Services;

use App\Jobs\SendHopEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ends what an account can still use somewhere else: its hub sessions, its remember-me cookies and,
 * through a queued session.revoked, its sessions in the apps. Used after a password change or reset,
 * turning two-factor off, changing the e-mail address, suspending the account and taking over an
 * unverified sign-up.
 */
final class AccountSessions
{
    /**
     * The part that belongs in a database transaction: delete the account's hub sessions (all but
     * `$exceptSessionId`) and rotate the remember token. Returns how many sessions were deleted.
     */
    public static function revoke(User $user, ?string $exceptSessionId = null): int
    {
        $query = DB::table('sessions')->where('user_id', $user->id);
        if ($exceptSessionId !== null) {
            $query->where('id', '!=', $exceptSessionId);
        }
        $deleted = $query->delete();
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

        return $deleted;
    }

    /**
     * Tell the apps to drop their sessions for the account. Call it after the transaction has committed: a
     * queue that cannot take the job is reported, and never undoes the change.
     */
    public static function announce(User $user): void
    {
        try {
            SendHopEvent::broadcast('session.revoked', ['sub' => (string) $user->id, 'hop_id' => (string) $user->id]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Revoke and announce in one go (outside a larger transaction). */
    public static function end(User $user, ?string $exceptSessionId = null): int
    {
        $deleted = self::revoke($user, $exceptSessionId);
        self::announce($user);

        return $deleted;
    }
}
