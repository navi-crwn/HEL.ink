<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('github_stats')) {
            return;
        }
        // The last good GitHub numbers per repo (hop:github-stats), so the home page
        // never calls GitHub: `payload` is numbers and names only, `etags` lets the next
        // run ask "changed?" without spending quota, `fetched_at` is the last confirmation.
        Schema::create('github_stats', function (Blueprint $table) {
            $table->id();
            $table->string('repo', 120)->unique();
            $table->json('payload')->nullable();
            $table->json('etags')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_stats');
    }
};
