<?php

namespace App\Support;

use App\Models\User;

/**
 * Where someone lands after signing in at the hub when nothing else asked for
 * a page (no SSO round trip waiting): the HOP ID dashboard, for everyone. An account with a role finds
 * "Open Imperium" in the top bar; the hub itself is for people, Imperium for admins.
 */
final class AfterLogin
{
    public static function home(User $user): string
    {
        // Everyone starts on the HOP ID dashboard; an account with a role finds "Open Imperium" in the top bar.
        return route('dashboard', absolute: false);
    }
}
