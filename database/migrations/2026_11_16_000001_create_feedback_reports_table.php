<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('feedback_reports')) {
            return;
        }
        Schema::create('feedback_reports', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 16); // problem | idea | other
            $table->string('status', 16)->default('new'); // new | read | done
            $table->string('app', 32);
            $table->string('page', 80)->nullable(); // the screen name the app sent, never an address
            $table->string('app_version', 24)->nullable();
            $table->text('message');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('username', 64)->nullable(); // copy taken when it was sent
            $table->string('ip_address', 45)->nullable(); // emptied after 30 days
            $table->string('user_agent', 300)->nullable();
            $table->string('viewport', 16)->nullable();
            $table->string('locale', 12)->nullable();
            $table->string('screenshot_path', 120)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index('app');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_reports');
    }
};
