<?php

namespace App\Http\Controllers;

use App\Services\Watchdog;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/** /status: what the watchdog saw in the last 24 hours, per app. */
class StatusController extends Controller
{
    public function __invoke(Watchdog $watchdog): View
    {
        // The page that says whether things are up must answer while the database is down: no numbers, no history.
        try {
            return view('status', ['status' => $watchdog->summary(), 'failures' => $watchdog->failures()]);
        } catch (QueryException $e) {
            report($e);

            return view('status', ['status' => $watchdog->emptySummary(), 'failures' => new Collection]);
        }
    }

    /** Public: an explicit allowlist, so no app secret or internal endpoint can reach it. */
    public function json(Watchdog $watchdog): JsonResponse
    {
        try {
            $summary = $watchdog->summary();
        } catch (QueryException $e) {
            report($e);
            $summary = $watchdog->emptySummary();
        }
        $apps = collect($summary)->map(fn (array $s) => [
            'slug' => $s['app']->slug,
            'name' => $s['app']->name,
            'url' => $s['app']->url,
            'ok' => $s['ok'],
            'checked_at' => $s['checked_at']?->toIso8601String(),
            'latency_ms' => $s['latency_ms'],
            'uptime' => $s['uptime'],
        ]);

        return response()->json(['apps' => $apps])->header('Cache-Control', 'no-store');
    }
}
