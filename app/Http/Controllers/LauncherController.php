<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Services\Watchdog;
use App\Support\HopApps;
use App\Support\HubDashboard;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** hub.hel.ink/dashboard: the account's home (apps, health, security, activity). */
class LauncherController extends Controller
{
    public function __invoke(Request $request, Watchdog $watchdog): View
    {
        $user = $request->user();
        $dashboard = new HubDashboard($user);
        $status = $watchdog->summary();

        return view('launcher', [
            'apps' => HopApps::all(),
            'status' => $status,
            'summary' => HubDashboard::status($status),
            'dashboard' => $dashboard,
            'health' => $user->isSuperAdmin() ? null : $dashboard->health(),
            'logins' => $dashboard->logins(),
            'activity' => $dashboard->activity(),
            'announcements' => Announcement::live()->limit(3)->get(),
        ]);
    }
}
