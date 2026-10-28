<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** The hub's migrations must run on a database LinkHop already set up, and again, without harm. */
test('migrations use their own bookkeeping table and are idempotent', function () {
    expect(config('database.migrations.table'))->toBe('migrations_hub');
    expect(Schema::hasTable('migrations_hub'))->toBeTrue();
    expect(Schema::hasColumn('api_tokens', 'scopes'))->toBeTrue();
    expect(Schema::hasColumn('users', 'two_factor_secret'))->toBeTrue();
    // Run the account migration a second time on the same schema: nothing to add, no error.
    $migration = require base_path('database/migrations/0001_01_01_000000_hub_account_tables.php');
    $migration->up();
    expect(Schema::hasTable('users'))->toBeTrue();
});

test('an existing LinkHop-style users table only gains the missing columns', function () {
    // A second, empty in-memory database stands in for a production schema LinkHop made.
    config()->set('database.connections.legacy', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
    DB::purge('legacy');
    $default = DB::getDefaultConnection();
    DB::setDefaultConnection('legacy');
    try {
        Schema::create('users', function ($t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->timestamps();
        });
        Schema::create('api_tokens', function ($t) {
            $t->id();
            $t->foreignId('user_id');
            $t->string('name');
            $t->string('token', 64)->unique();
            $t->integer('rate_limit')->default(100);
            $t->timestamps();
        });
        DB::table('users')->insert(['name' => 'Old', 'email' => 'old@example.com', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        $migration = require base_path('database/migrations/0001_01_01_000000_hub_account_tables.php');
        $migration->up();
        expect(Schema::hasColumns('users', ['username', 'role', 'status', 'two_factor_secret', 'google_id', 'avatar_path']))->toBeTrue();
        expect(Schema::hasColumn('api_tokens', 'scopes'))->toBeTrue();
        expect(DB::table('users')->where('email', 'old@example.com')->value('status'))->toBe('active');
        expect(Schema::hasTable('login_histories'))->toBeTrue();
    } finally {
        DB::setDefaultConnection($default);
    }
});
