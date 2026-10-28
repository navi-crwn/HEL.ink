<?php

namespace App\Services;

use App\Mail\ServiceAlert;
use App\Models\ServiceCheck;
use App\Support\HopApp;
use App\Support\HopApps;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Probes every app's /up (and its optional functional check URL), records
 * the result in service_checks and raises an alert after N failures in a row.
 * One alert per outage; a recovery message when the app answers again.
 */
class Watchdog
{
    /** Runs every probe once. @return array<string, array<string, bool>> slug => kind => ok */
    public function run(): array
    {
        $results = [];
        foreach (HopApps::all() as $app) {
            $results[$app->slug]['health'] = $this->probe($app, 'health', $app->healthUrl());
            if ($app->check) {
                $results[$app->slug]['check'] = $this->probe($app, 'check', $app->check);
            }
        }
        ServiceCheck::where('checked_at', '<', now()->subDays((int) config('hop.watch.keep_days', 7)))->delete();

        return $results;
    }

    private function probe(HopApp $app, string $kind, string $url): bool
    {
        $started = microtime(true);
        $code = null;
        $error = null;
        try {
            $res = Http::timeout((int) config('hop.watch.timeout', 8))->withHeaders(['User-Agent' => 'HOP-watchdog/1'])->get($url);
            $code = $res->status();
            $ok = $res->successful();
            if (! $ok) {
                $error = 'HTTP '.$code;
            }
        } catch (\Throwable $e) {
            $ok = false;
            $error = mb_substr($e->getMessage(), 0, 300);
        }
        ServiceCheck::create([
            'app_slug' => $app->slug,
            'kind' => $kind,
            'ok' => $ok,
            'status_code' => $code,
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
            'error' => $error,
            'checked_at' => now(),
        ]);
        $this->track($app, $kind, $ok, $error);

        return $ok;
    }

    /** Counts failures in a row (cache) and sends the alert at the threshold, the recovery after it. */
    private function track(HopApp $app, string $kind, bool $ok, ?string $error): void
    {
        $key = "hop:watch:{$app->slug}:{$kind}";
        $threshold = (int) config('hop.watch.failures_before_alert', 3);
        $failures = (int) Cache::get($key.':failures', 0);
        $alerted = (bool) Cache::get($key.':alerted', false);
        if ($ok) {
            Cache::forget($key.':failures');
            if ($alerted) {
                Cache::forget($key.':alerted');
                $this->notify("{$app->name} is back", "{$app->name} ({$kind}) answers again.");
            }

            return;
        }
        $failures++;
        Cache::put($key.':failures', $failures, now()->addDay());
        if ($failures >= $threshold && ! $alerted) {
            Cache::put($key.':alerted', true, now()->addWeek());
            $this->notify("{$app->name} is down", "{$app->name} ({$kind}) failed {$failures} times in a row. Last error: ".($error ?: 'unknown').'. URL: '.($kind === 'health' ? $app->healthUrl() : $app->check));
        }
    }

    private function notify(string $subject, string $text): void
    {
        if ($to = config('hop.watch.alert_email')) {
            try {
                Mail::to($to)->send(new ServiceAlert($subject, $text));
            } catch (\Throwable $e) {
                Log::error('watchdog: mail failed', ['error' => $e->getMessage()]);
            }
        }
        $token = config('hop.watch.telegram_token');
        $chat = config('hop.watch.telegram_chat_id');
        if ($token && $chat) {
            try {
                Http::timeout(8)->post("https://api.telegram.org/bot{$token}/sendMessage", ['chat_id' => $chat, 'text' => "⚠️ {$subject}\n{$text}"]);
            } catch (\Throwable $e) {
                Log::error('watchdog: telegram failed', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * The latest result per app, for the launcher, the home page and /status.
     *
     * @return array<string, array{app: HopApp, ok: ?bool, checked_at: ?Carbon, latency_ms: ?int, error: ?string, uptime: ?float}>
     */
    public function summary(): array
    {
        $out = [];
        foreach (HopApps::all() as $app) {
            $last = ServiceCheck::where('app_slug', $app->slug)->where('kind', 'health')->latest('checked_at')->first();
            $day = ServiceCheck::where('app_slug', $app->slug)->where('kind', 'health')->where('checked_at', '>=', now()->subDay());
            $total = (clone $day)->count();
            $out[$app->slug] = [
                'app' => $app,
                'ok' => $last?->ok,
                'checked_at' => $last?->checked_at,
                'latency_ms' => $last?->latency_ms,
                'error' => $last?->error,
                'uptime' => $total ? round((clone $day)->where('ok', true)->count() * 100 / $total, 2) : null,
            ];
        }

        return $out;
    }

    /** Failed probes in the last 24 hours, newest first. */
    public function history(int $limit = 50)
    {
        return ServiceCheck::where('ok', false)->where('checked_at', '>=', now()->subDay())->latest('checked_at')->limit($limit)->get();
    }
}
