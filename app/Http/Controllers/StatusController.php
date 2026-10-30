<?php

namespace App\Http\Controllers;

use App\Services\Watchdog;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/** /status: what the watchdog saw in the last 24 hours, per app. */
class StatusController extends Controller
{
    public function __invoke(Watchdog $watchdog): View
    {
        return view('status', ['status' => $watchdog->summary(), 'history' => $watchdog->history()]);
    }

    /** Public: an explicit allowlist, so no app secret or internal endpoint can reach it. */
    public function json(Watchdog $watchdog): JsonResponse
    {
        $apps = collect($watchdog->summary())->map(fn (array $s) => [
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
