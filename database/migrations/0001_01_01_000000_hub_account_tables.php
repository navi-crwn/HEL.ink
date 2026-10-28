<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The account tables the hub owns in the shared HOP database: users and
 * everything about signing in, plus Laravel's own tables. In production these
 * tables already exist (LinkHop created them), so every step here checks
 * first: a fresh database gets the full tables, an existing one only the
 * columns it is missing. Nothing is ever dropped or altered in place.
 *
 * App tables (links, bio_pages, …) are not here: LinkHop and BioHop own those.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });
        }
        $this->addColumns('users', [
            'google_id' => fn (Blueprint $t) => $t->string('google_id')->nullable()->unique(),
            'username' => fn (Blueprint $t) => $t->string('username', 30)->nullable()->unique(),
            'avatar' => fn (Blueprint $t) => $t->string('avatar')->nullable(),
            'avatar_path' => fn (Blueprint $t) => $t->string('avatar_path')->nullable(),
            'bio' => fn (Blueprint $t) => $t->string('bio', 300)->nullable(),
            'website' => fn (Blueprint $t) => $t->string('website', 200)->nullable(),
            'location' => fn (Blueprint $t) => $t->string('location', 100)->nullable(),
            'locale' => fn (Blueprint $t) => $t->string('locale', 10)->nullable(),
            'timezone' => fn (Blueprint $t) => $t->string('timezone', 64)->nullable(),
            'show_owner' => fn (Blueprint $t) => $t->boolean('show_owner')->default(true),
            'profile_version' => fn (Blueprint $t) => $t->unsignedInteger('profile_version')->default(1),
            'role' => fn (Blueprint $t) => $t->string('role', 20)->default('user'),
            'status' => fn (Blueprint $t) => $t->string('status', 20)->default('active'),
            'last_login_ip' => fn (Blueprint $t) => $t->string('last_login_ip', 45)->nullable(),
            'last_login_country' => fn (Blueprint $t) => $t->string('last_login_country', 2)->nullable(),
            'last_login_city' => fn (Blueprint $t) => $t->string('last_login_city')->nullable(),
            'last_login_provider' => fn (Blueprint $t) => $t->string('last_login_provider')->nullable(),
            'last_login_device' => fn (Blueprint $t) => $t->string('last_login_device')->nullable(),
            'last_login_browser' => fn (Blueprint $t) => $t->string('last_login_browser')->nullable(),
            'last_login_at' => fn (Blueprint $t) => $t->timestamp('last_login_at')->nullable(),
            'two_factor_secret' => fn (Blueprint $t) => $t->string('two_factor_secret')->nullable(),
            'two_factor_recovery_codes' => fn (Blueprint $t) => $t->text('two_factor_recovery_codes')->nullable(),
            'two_factor_confirmed_at' => fn (Blueprint $t) => $t->timestamp('two_factor_confirmed_at')->nullable(),
        ]);

        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }
        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
        if (! Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        }
        if (! Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
        }
        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }
        if (! Schema::hasTable('job_batches')) {
            Schema::create('job_batches', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name');
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
            });
        }
        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('login_histories')) {
            Schema::create('login_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('ip_address')->nullable();
                $table->string('country', 2)->nullable();
                $table->string('country_name')->nullable();
                $table->string('city')->nullable();
                $table->string('region')->nullable();
                $table->string('provider')->nullable();
                $table->string('isp')->nullable();
                $table->boolean('is_proxy')->default(false);
                $table->string('proxy_type')->nullable();
                $table->integer('proxy_confidence')->nullable();
                $table->string('device')->nullable();
                $table->string('browser')->nullable();
                $table->timestamp('logged_in_at');
                $table->timestamps();
                $table->index(['user_id', 'logged_in_at']);
            });
        }
        if (! Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('action');
                $table->string('model');
                $table->string('model_id')->nullable();
                $table->text('description');
                $table->string('ip_address')->nullable();
                $table->string('user_agent')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'created_at']);
            });
        }
        if (! Schema::hasTable('api_tokens')) {
            Schema::create('api_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->integer('rate_limit')->default(100);
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'token']);
            });
        }
        // Which apps a token may call: ["link:*"], ["bio:read", "link:create"], … Null: every scope.
        $this->addColumns('api_tokens', [
            'scopes' => fn (Blueprint $t) => $t->json('scopes')->nullable(),
        ]);

        // Roles (owner, admin, moderator, support; or admin of one app) and released usernames.
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('role', 32);
                $table->string('app_slug', 32)->nullable();
                $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['user_id', 'role', 'app_slug']);
            });
        }
        if (! Schema::hasTable('username_history')) {
            Schema::create('username_history', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('username', 30)->index();
                $table->timestamp('released_at');
                $table->timestamps();
            });
        }
    }

    /** No down(): these tables are shared with the other apps and are never dropped from here. */
    public function down(): void {}

    /** @param  array<string, callable(Blueprint): mixed>  $columns */
    private function addColumns(string $table, array $columns): void
    {
        $missing = array_filter($columns, fn ($_, $name) => ! Schema::hasColumn($table, $name), ARRAY_FILTER_USE_BOTH);
        if ($missing === []) {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($missing) {
            foreach ($missing as $add) {
                $add($t);
            }
        });
    }
};
