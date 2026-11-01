<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class ProtectPermanentSuperadmin
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }
        $target = $request->route('user');
        $adminMutation = $request->routeIs('admin.users.*') && $target instanceof User && $target->isSuperAdmin();
        $selfMutation = $request->user()?->isSuperAdmin() && $request->routeIs(
            'profile.*', 'password.update', 'password.create', 'onboarding.set-password',
            'settings.2fa.*', 'api-tokens.store', 'api-tokens.destroy'
        );
        if ($adminMutation || $selfMutation) {
            abort(403, 'Superadmin accounts are permanent and read-only, including for the account itself.');
        }

        return $next($request);
    }
}
