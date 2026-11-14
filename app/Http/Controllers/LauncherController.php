<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\User;
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
        [$announcement, $announcementsMore] = self::announcementCard($user);

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
            'announcement' => $announcement,
            'announcementsMore' => $announcementsMore,
        ]);
    }

    /**
     * The one announcement card of the dashboard, like in the other apps: a critical one first (it cannot be hidden),
     * else the newest the person has not hidden for a week. The second value is how many more are live and not
     * hidden, so the card can point to the list instead of silently dropping them.
     *
     * @return array{0: Announcement|null, 1: int}
     */
    private static function announcementCard(User $user): array
    {
        $hidden = Reminders::hidden($user);
        $shown = Announcement::live()->limit(20)->get()
            ->reject(fn (Announcement $a) => $a->level !== 'critical' && in_array(Reminders::announcementKey($a), $hidden, true))
            ->sortBy([fn ($a, $b) => ($b->level === 'critical') <=> ($a->level === 'critical')])
            ->values();

        return [$shown->first(), max(0, $shown->count() - 1)];
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
