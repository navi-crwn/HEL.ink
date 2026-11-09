<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a person hid one of HOP ID's reminder cards (account checklist, two-factor,
 * recovery codes): one row per account and reminder. The card stays away for seven
 * days from `dismissed_at`, then shows again while its task is still open.
 * The name says "hub" because the database is shared with LinkHop and BioHop.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hub_reminder_dismissals')) {
            return;
        }
        Schema::create('hub_reminder_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 64);
            $table->timestamp('dismissed_at');
            $table->timestamps();
            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hub_reminder_dismissals');
    }
};
