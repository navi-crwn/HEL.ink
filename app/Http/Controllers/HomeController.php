<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Services\Watchdog;
use App\Support\HopApps;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\View\View;

/** hel.ink: Hop Easy Link, one hop to every app. */
class HomeController extends Controller
{
    public function __invoke(Watchdog $watchdog): View
    {
        // The home page must render while the database is down: no status numbers, no announcements.
        try {
            $status = $watchdog->summary();
            $announcements = Announcement::live()->whereNull('app_slug')->limit(2)->get();
        } catch (QueryException $e) {
            report($e);
            $status = $watchdog->emptySummary();
            $announcements = new Collection;
        }

        return view('home', [
            'apps' => HopApps::all(),
            'status' => $status,
            'announcements' => $announcements,
        ]);
    }
}
