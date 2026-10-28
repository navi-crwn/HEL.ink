<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HOP ID's app registry (`apps`: Imperium, NoteHop, … with their SSO secret
 * and capabilities), the admin audit trail and admin notes. They belong to
 * the hub, the HOP ID provider. In production they already exist (LinkHop
 * created them before the split), so every step only runs where missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('apps')) {
            Schema::create('apps', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 32)->unique();
                $table->string('name', 60);
                $table->string('url', 200);
                $table->string('callback_path', 100)->default('/auth/sso/callback');
                $table->string('start_path', 100)->default('/auth/sso');
                $table->json('pages')->nullable();
                $table->string('continue_page', 40)->default('default');
                $table->string('menu_label', 40)->nullable();
                $table->string('icon', 40)->nullable();
                $table->string('color', 16)->nullable();
                $table->string('status', 16)->default('active');
                $table->text('secret');
                $table->text('secret_prev')->nullable();
                $table->timestamp('secret_prev_until')->nullable();
                $table->json('capabilities')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(100);
                $table->timestamps();
            });
        }
        if (! Schema::hasColumn('apps', 'hidden')) {
            Schema::table('apps', function (Blueprint $table) {
                // Registered (SSO, signed API) but kept out of menus: Imperium.
                $table->boolean('hidden')->default(false);
            });
        }
        if (! Schema::hasTable('hop_audit')) {
            Schema::create('hop_audit', function (Blueprint $table) {
                $table->id();
                $table->string('actor', 64)->default('system');
                $table->string('app_slug', 32);
                $table->string('action', 40);
                $table->string('target_type', 20)->default('user');
                $table->string('target_id', 64)->index();
                $table->string('reason', 500)->nullable();
                $table->json('params')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
        if (! Schema::hasTable('hop_user_notes')) {
            Schema::create('hop_user_notes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('author', 64)->default('system');
                $table->text('text');
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        // Shared production tables: never dropped from here.
    }
};
