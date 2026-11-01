<?php

namespace App\Support;

use App\Models\ServiceCheck;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Uptime per app over the last 30 days, from the watchdog's own probes (service_checks,
 * kind "health"): the percentage and one bucket per day (up, down or none). No third
 * party. Cached for a few minutes because a month of per-minute probes is a lot of rows.
 */
class UptimeStats
{
    private const CACHE_KEY = 'hop:uptime-stats';

    /**
     * @return array{fetched_at: ?string, days: int, apps: list<array{slug: string, name: string, uptime: ?float, days_measured: int, days: list<array{date: string, status: string, uptime: ?float}>}>}
     */
    public function all(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, (int) config('hop.uptime.cache_seconds', 300), fn () => $this->build());
        } catch (\Throwable) {
            return ['fetched_at' => null, 'days' => $this->window(), 'apps' => []];
        }
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function window(): int
    {
        return max(1, (int) config('hop.uptime.days', 30));
    }

    private function build(): array
    {
        $window = $this->window();
        $today = now()->startOfDay();
        $from = $today->copy()->subDays($window - 1);
        $downBelow = (float) config('hop.uptime.down_below', 99.0);
        $apps = [];
        foreach (HopApps::all() as $app) {
            $rows = ServiceCheck::query()
                ->where('app_slug', $app->slug)->where('kind', 'health')->where('checked_at', '>=', $from)
                ->selectRaw('DATE(checked_at) as day, COUNT(*) as total, SUM(CASE WHEN ok THEN 1 ELSE 0 END) as up')
                ->groupBy('day')->get()->keyBy(fn ($r) => (string) $r->day);
            $days = [];
            $total = 0;
            $up = 0;
            for ($i = 0; $i < $window; $i++) {
                $date = $from->copy()->addDays($i)->toDateString();
                $row = $rows->get($date);
                $n = (int) ($row->total ?? 0);
                $u = (int) ($row->up ?? 0);
                $total += $n;
                $up += $u;
                $pct = $n ? round($u * 100 / $n, 2) : null;
                $days[] = ['date' => $date, 'status' => $pct === null ? 'none' : ($pct < $downBelow ? 'down' : 'up'), 'uptime' => $pct];
            }
            $apps[] = [
                'slug' => $app->slug,
                'name' => $app->name,
                'uptime' => $total ? round($up * 100 / $total, 2) : null,
                'days_measured' => count(array_filter($days, fn ($d) => $d['status'] !== 'none')),
                'days' => $days,
            ];
        }
        $last = ServiceCheck::where('kind', 'health')->max('checked_at');

        return ['fetched_at' => $last ? Carbon::parse($last)->toIso8601String() : null, 'days' => $window, 'apps' => $apps];
    }
}
