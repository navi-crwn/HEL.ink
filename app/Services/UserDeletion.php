<?php

namespace App\Services;

use App\Models\User;
use App\Support\Avatar;
use Illuminate\Support\Facades\DB;

/**
 * Removes a HOP ID: the hub's own rows (sessions, sign-ins, activity, API
 * tokens, roles) and the user. Rows in the other apps' tables go with it
 * through their foreign keys (links, bio pages, … cascade on user_id); each
 * app cleans up its own files from its account.deleted handling.
 */
class UserDeletion
{
    public function delete(User $user): void
    {
        Avatar::delete($user);
        DB::transaction(function () use ($user) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('login_histories')->where('user_id', $user->id)->delete();
            DB::table('activity_logs')->where('user_id', $user->id)->delete();
            DB::table('api_tokens')->where('user_id', $user->id)->delete();
            DB::table('roles')->where('user_id', $user->id)->delete();
            $user->delete();
        });
    }
}
