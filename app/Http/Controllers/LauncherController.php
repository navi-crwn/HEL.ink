<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Services\AppSummaries;
use App\Services\Watchdog;
use App\Support\HopApps;
use App\Support\HubDashboard;
use App\Support\Reminders;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** hub.hel.ink/dashboard: the account's home (apps, health, security, activity). */
class LauncherController extends Controller
{
    public function __invoke(Request $request, Watchdog $watchdog, AppSummaries $summaries): View
    {
        $user = $request->user();
        $dashboard = new HubDashboard($user);
        $status = $watchdog->summary();
        $apps = HopApps::all();
        $health = $user->isSuperAdmin() ? null : $dashboard->health();

        return view('launcher', [
            'apps' => $apps,
            'appNumbers' => $summaries->for($user, $apps, $status) + self::storesNothing(),
            'status' => $status,
            'summary' => HubDashboard::status($status),
            'dashboard' => $dashboard,
            'health' => $health,
            // Reminder cards to draw: task still open and not hidden in the last seven days.
            'reminders' => Reminders::due($user, $dashboard, $health),
            'logins' => $dashboard->logins(),
            'activity' => $dashboard->activity(),
            'announcements' => Announcement::live()->limit(3)->get(),
        ]);
    }

    /**
     * The fixed line of an app that stores nothing (FileHop), so its card is as tall as the others and says why it has no counts.
     *
     * @return array<string, list<array{label: string, value: string}>>
     */
    private static function storesNothing(): array
    {
        $rows = [
            ['label' => 'Uploads', 'value' => __('Never')],
            ['label' => 'Stored', 'value' => '0 B'],
        ];

        return array_fill_keys((array) config('hop.card_facts', []), $rows);
    }
}
