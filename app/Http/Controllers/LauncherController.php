<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Services\Watchdog;
use App\Support\HopApps;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** hub.hel.ink/dashboard: one card per app, with its status and "Open". */
class LauncherController extends Controller
{
    public function __invoke(Request $request, Watchdog $watchdog): View
    {
        return view('launcher', [
            'apps' => HopApps::all(),
            'status' => $watchdog->summary(),
            'announcements' => Announcement::live()->limit(3)->get(),
        ]);
    }
}
