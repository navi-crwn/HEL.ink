<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('service_checks')) {
            return;
        }
        Schema::create('service_checks', function (Blueprint $table) {
            $table->id();
            $table->string('app_slug', 32);
            // "health" (/up) or "check" (the app's functional test URL).
            $table->string('kind', 16)->default('health');
            $table->boolean('ok');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('error', 300)->nullable();
            $table->timestamp('checked_at')->index();
            $table->index(['app_slug', 'kind', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_checks');
    }
};
