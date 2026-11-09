<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A sign-in row is either a real sign-in at the hub (`login`: password, Google, an app's
 * own sign-in form) or a single sign-on hand-off into an app (`sso`). Until now both were
 * one list of five rows, so opening five apps pushed every real sign-in out of view.
 * Existing rows whose provider is an app name were hand-offs (LoginRecorder stores the
 * app name there); everything else was a sign-in.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('login_histories')) {
            return;
        }
        if (! Schema::hasColumn('login_histories', 'kind')) {
            Schema::table('login_histories', function (Blueprint $table) {
                $table->string('kind', 8)->default('login')->after('user_id');
            });
        }
        $apps = [];
        foreach ((array) config('hop.apps', []) as $app) {
            if (is_array($app) && ! empty($app['name'])) {
                $apps[] = (string) $app['name'];
            }
        }
        if (Schema::hasTable('apps')) {
            $apps = array_merge($apps, DB::table('apps')->pluck('name')->map(fn ($n) => (string) $n)->all());
        }
        $apps = array_values(array_unique(array_filter($apps)));
        if ($apps !== []) {
            DB::table('login_histories')->where('kind', 'login')->whereIn('provider', $apps)->update(['kind' => 'sso']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('login_histories') && Schema::hasColumn('login_histories', 'kind')) {
            Schema::table('login_histories', function (Blueprint $table) {
                $table->dropColumn('kind');
            });
        }
    }
};
