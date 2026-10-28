<?php

namespace App\Http\Controllers;

use App\Support\HopApps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $loginHistories = DB::table('login_histories')
            ->where('user_id', $user->id)
            ->orderBy('logged_in_at', 'desc')
            ->limit(5)
            ->get();
        $activityQuery = DB::table('activity_logs')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc');
        if ($request->filled('activity_from')) {
            $activityQuery->whereDate('created_at', '>=', $request->activity_from);
        }
        if ($request->filled('activity_to')) {
            $activityQuery->whereDate('created_at', '<=', $request->activity_to);
        }

        return view('settings', [
            'loginHistories' => $loginHistories,
            'activityLogs' => $activityQuery->limit(50)->get(),
            'apps' => HopApps::all(),
        ]);
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
            'login_history' => $user->loginHistories()->latest('logged_in_at')->get(['logged_in_at', 'ip_address', 'country', 'city', 'device', 'browser', 'provider']),
            'activity_log' => $user->activityLogs()->latest()->get(['created_at', 'action', 'model', 'description']),
            'api_tokens' => $user->apiTokens()->get(['name', 'scopes', 'created_at', 'last_used_at', 'expires_at']),
            'app_exports' => collect(HopApps::all())->filter->export->mapWithKeys(fn ($a) => [$a->slug => $a->exportUrl()]),
        ];

        return response()->streamDownload(
            fn () => print (json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            'hop-account-'.now()->format('Y-m-d').'.json',
            ['Content-Type' => 'application/json']
        );
    }
}
