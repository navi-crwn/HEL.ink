<?php

namespace App\Support;

use App\Models\User;

/**
 * Where someone lands after signing in at the hub when nothing else asked for
 * a page (no SSO round trip waiting): admins go to Imperium, the admin control
 * panel, when it is configured; everyone else to the launcher. The hub
 * dashboard stays one click away for admins (sidebar).
 */
final class AfterLogin
{
    public static function home(User $user): string
    {
        $control = (string) config('hop.control_url');

        return $control !== '' && $user->isAdmin() ? $control : route('dashboard', absolute: false);
    }
}
