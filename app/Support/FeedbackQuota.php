<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Counters for the Feedback form that can be given back. A counter is a number in the cache that lives for its window
 * (an hour, a day). `take` raises every counter of a list at once and refuses when any is over its maximum, leaving them as
 * they were; `give` lowers them again. The form reserves a place before the expensive work and gives it back when the request
 * ends without a saved report, so a refused picture or a failed save costs the visitor nothing, while requests that run at
 * the same time are all counted while they run.
 */
final class FeedbackQuota
{
    /**
     * @param  list<array{0: string, 1: int, 2: int}>  $limits  [counter name, most allowed, window in seconds]
     * @return ?int null when the places were taken; else the seconds until the counter that was full frees up
     */
    public static function take(array $limits): ?int
    {
        $taken = [];
        foreach ($limits as $limit) {
            [$key, $max, $seconds] = $limit;
            Cache::add($key, 0, $seconds);
            Cache::add($key.':until', time() + $seconds, $seconds);
            $taken[] = $limit;
            if ((int) Cache::increment($key) > $max) {
                self::give($taken);

                return self::wait($key);
            }
        }

        return null;
    }

    /** @param  list<array{0: string, 1: int, 2: int}>  $limits */
    public static function give(array $limits): void
    {
        foreach ($limits as [$key]) {
            if ((int) Cache::get($key, 0) > 0) {
                Cache::decrement($key);
            }
        }
    }

    public static function used(string $key): int
    {
        return (int) Cache::get($key, 0);
    }

    /** Sets a counter (for tests and for an operator who wants to block an address by hand). */
    public static function set(string $key, int $count, int $seconds): void
    {
        Cache::put($key, $count, $seconds);
        Cache::put($key.':until', time() + $seconds, $seconds);
    }

    public static function clear(string $key): void
    {
        Cache::forget($key);
        Cache::forget($key.':until');
    }

    public static function wait(string $key): int
    {
        return max(1, (int) Cache::get($key.':until', time() + 60) - time());
    }
}
