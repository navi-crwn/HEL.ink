<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Content-bound acceptance records for local published legal artifacts. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('legal_acceptances')) {
            throw new RuntimeException('Refusing to adopt a preexisting legal_acceptances table; inspect the shared database explicitly.');
        }
        Schema::create('legal_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('policy', 40)->default('terms');
            $table->string('version', 64);
            $table->string('sha256', 64);
            $table->string('source', 20); // web | oauth | app
            $table->timestamp('accepted_at');
            $table->timestamps();
            $table->unique(['user_id', 'policy', 'version']);
        });
    }

    /** Never drop evidence or a preexisting shared-database table. */
    public function down(): void
    {
        // Intentionally non-destructive. Isolated disposable databases may be
        // removed by their owner; this migration never performs that action.
    }
};
