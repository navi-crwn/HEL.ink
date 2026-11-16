<?php

namespace App\Http\Controllers;

use App\Models\FeedbackReport;
use App\Models\LoginHistory;
use App\Support\HopApps;
use App\Support\Input;
use App\Support\Reminders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        // Real sign-ins and single sign-on hand-offs into the apps are two lists: opening five
        // apps must not push the sign-ins (what people check for intruders) out of view.
        $loginHistories = LoginHistory::where('user_id', $user->id)->signIns()
            ->orderByDesc('logged_in_at')->orderByDesc('id')
            ->limit(LoginHistory::KEEP)
            ->get();
        $appSignIns = LoginHistory::where('user_id', $user->id)->handoffs()
            ->orderByDesc('logged_in_at')->orderByDesc('id')
            ->limit(LoginHistory::KEEP)
            ->get();
        $activityQuery = DB::table('activity_logs')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc');
        if ($from = Input::text($request->query('activity_from'))) {
            $activityQuery->whereDate('created_at', '>=', $from);
        }
        if ($to = Input::text($request->query('activity_to'))) {
            $activityQuery->whereDate('created_at', '<=', $to);
        }

        return view('settings', [
            'loginHistories' => $loginHistories,
            'appSignIns' => $appSignIns,
            'activityLogs' => $activityQuery->limit(50)->get(),
            'apps' => HopApps::all(),
        ]);
    }

    /** The reports this account sent: what kind, about which app, the text and the date (the screenshot is not part of it). */
    private function feedbackExport(int $userId): array
    {
        if (! Schema::hasTable('feedback_reports')) {
            return [];
        }

        return FeedbackReport::where('user_id', $userId)->orderBy('id')->get()
            ->map(fn (FeedbackReport $r) => ['kind' => $r->kind, 'app' => $r->app, 'message' => $r->message, 'created_at' => $r->created_at?->toIso8601String()])->all();
    }

    /**
     * Everything the hub stores about the account (right of access): profile,
     * roles, recent sign-ins, the activity log and the API token names. Each
     * app exports its own content (links, bio pages, images) from its settings.
     */
    public function exportAccount(Request $request)
    {
        $user = $request->user();
        $data = [
            'exported_at' => now()->toIso8601String(),
            'account' => $user->only(['username', 'name', 'email', 'bio', 'website', 'location', 'locale', 'timezone', 'show_owner', 'created_at', 'email_verified_at', 'last_login_at']),
            'roles' => $user->roleNames(),
            'login_history' => $user->loginHistories()->latest('logged_in_at')->get(['kind', 'logged_in_at', 'ip_address', 'country', 'city', 'device', 'browser', 'provider']),
            'activity_log' => $user->activityLogs()->latest()->get(['created_at', 'action', 'model', 'description']),
            'api_tokens' => $user->apiTokens()->get(['name', 'scopes', 'created_at', 'last_used_at', 'expires_at']),
            'hidden_reminders' => Reminders::export($user),
            'feedback' => $this->feedbackExport($user->id),
            'app_exports' => collect(HopApps::all())->filter->export->mapWithKeys(fn ($a) => [$a->slug => $a->exportUrl()]),
        ];

        return response()->streamDownload(
            fn () => print (json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            'hop-account-'.now()->format('Y-m-d').'.json',
            ['Content-Type' => 'application/json']
        );
    }
}
