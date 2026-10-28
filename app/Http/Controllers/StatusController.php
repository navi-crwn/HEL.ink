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

    public function json(Watchdog $watchdog): JsonResponse
    {
        return response()->json(['apps' => $watchdog->summary()])->header('Cache-Control', 'no-store');
    }
}
