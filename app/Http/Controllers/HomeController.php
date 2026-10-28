<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Services\Watchdog;
use App\Support\HopApps;
use Illuminate\View\View;

/** hel.ink: Hop Easy Link, one hop to every app. */
class HomeController extends Controller
{
    public function __invoke(Watchdog $watchdog): View
    {
        return view('home', [
            'apps' => HopApps::all(),
            'status' => $watchdog->summary(),
            'announcements' => Announcement::live()->whereNull('app_slug')->limit(2)->get(),
        ]);
    }
}
