<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendHopEvent;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use App\Services\UserDeletion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Accounts across every HOP app: search, suspend, reinstate, make admin,
 * delete. Only the hub writes to `users`; the apps see the change at once.
 */
class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $users = User::query()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('email', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%")
                ->orWhere('username', 'like', "%{$q}%")))
            ->when($request->query('status') === 'suspended', fn ($query) => $query->where('status', User::STATUS_SUSPENDED))
            ->orderByDesc('created_at')
            ->paginate(25)->withQueryString();

        return view('admin.users.index', ['users' => $users, 'q' => $q]);
    }

    public function show(User $user): View
    {
        return view('admin.users.show', [
            'user' => $user,
            'logins' => $user->loginHistories()->latest('logged_in_at')->limit(10)->get(),
            'activity' => $user->activityLogs()->latest()->limit(20)->get(),
            'tokens' => $user->apiTokens()->get(),
        ]);
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        if ($user->isOwner() || $user->is($request->user())) {
            return back()->withErrors(['user' => 'This account cannot be suspended.']);
        }
        $user->forceFill(['status' => User::STATUS_SUSPENDED])->save();
        $user->apiTokens()->delete();
        ActivityLog::log('suspended', 'User', $user->id, 'Suspended by '.$request->user()->email);

        return back()->with('status', $user->email.' suspended. Every HOP app refuses the account from now on.');
    }

    public function activate(Request $request, User $user): RedirectResponse
    {
        $user->forceFill(['status' => User::STATUS_ACTIVE])->save();
        ActivityLog::log('activated', 'User', $user->id, 'Reinstated by '.$request->user()->email);

        return back()->with('status', $user->email.' is active again.');
    }

    public function role(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);
        $data = $request->validate(['role' => ['required', 'in:'.implode(',', Role::GLOBAL)], 'grant' => ['required', 'boolean']]);
        if ($data['role'] === Role::OWNER) {
            return back()->withErrors(['user' => 'Ownership is set with SUPERADMIN_EMAIL or hop:owner, not here.']);
        }
        if ($request->boolean('grant')) {
            $user->roles()->firstOrCreate(['role' => $data['role'], 'app_slug' => null], ['granted_by' => $request->user()->id]);
        } else {
            $user->roles()->where('role', $data['role'])->whereNull('app_slug')->delete();
        }
        $user->unsetRelation('roles');
        SendHopEvent::broadcast('profile.updated', $user->hopProfile());

        return back()->with('status', 'Roles updated.');
    }

    public function destroy(Request $request, User $user, UserDeletion $deletion): RedirectResponse
    {
        if ($user->isOwner() || $user->is($request->user())) {
            return back()->withErrors(['user' => 'This account cannot be deleted.']);
        }
        $deletion->delete($user);

        return redirect()->route('admin.users.index')->with('status', 'Account deleted.');
    }
}
