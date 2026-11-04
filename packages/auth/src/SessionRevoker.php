<?php

namespace Hop\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Ends every way an account can still be signed in at this app: the session
 * rows or files that name the account, and the remember cookie (the "remember"
 * sign-in of hop/auth lasts the guard's cookie lifetime, 400 days by default, and
 * brings the person straight back in once the session is gone unless its token changes).
 *
 * Sessions can be found in the `database` and `file` drivers. Other drivers
 * (redis, memcached, cookie, …) hold no index by account, so `complete` is false
 * for them: the remember token is still rotated, and the session lapses at its lifetime.
 */
final class SessionRevoker
{
    /** @return array{sessions: int, remember_rotated: bool, driver: string, complete: bool} */
    public static function revoke(int $userId): array
    {
        $driver = (string) config('session.driver');
        $rotated = self::rotateRememberToken($userId);
        [$deleted, $complete] = match ($driver) {
            'database' => [self::deleteDatabaseSessions($userId), true],
            'file' => [self::deleteFileSessions($userId), true],
            default => [0, false],
        };
        if (! $complete) {
            Log::warning('hop-auth: sessions of an account cannot be listed with this session driver; only the remember token was rotated', ['driver' => $driver, 'user' => $userId]);
        }

        return ['sessions' => $deleted, 'remember_rotated' => $rotated, 'driver' => $driver, 'complete' => $complete];
    }

    private static function rotateRememberToken(int $userId): bool
    {
        $model = (string) config('hop-auth.user_model', 'App\\Models\\User');
        if (! class_exists($model)) {
            return false;
        }

        // Straight on the table: no model events, no new updated_at for a security reset.
        return $model::query()->whereKey($userId)->toBase()->update(['remember_token' => Str::random(60)]) > 0;
    }

    private static function deleteDatabaseSessions(int $userId): int
    {
        return DB::connection(config('session.connection'))->table((string) config('session.table', 'sessions'))->where('user_id', $userId)->delete();
    }

    private static function deleteFileSessions(int $userId): int
    {
        $dir = (string) config('session.files');
        $deleted = 0;
        foreach (is_dir($dir) ? (glob(rtrim($dir, '/').'/*') ?: []) : [] as $file) {
            if (is_file($file) && self::fileBelongsTo($file, $userId) && @unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /** The session store keeps who signed in under a `login_<guard>_<hash>` key. */
    private static function fileBelongsTo(string $file, int $userId): bool
    {
        $raw = @file_get_contents($file);
        if (! is_string($raw) || $raw === '') {
            return false;
        }
        try {
            if (config('session.encrypt')) {
                $raw = app('encrypter')->decrypt($raw, false);
            }
            $data = config('session.serialization') === 'json' ? json_decode($raw, true) : @unserialize($raw, ['allowed_classes' => false]);
        } catch (\Throwable) {
            return false;
        }
        foreach (is_array($data) ? $data : [] as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'login_') && (is_int($value) || is_string($value)) && (string) $value === (string) $userId) {
                return true;
            }
        }

        return false;
    }
}
