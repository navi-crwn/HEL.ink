<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The language set is nine: `zh` and `pt` are now `zh-CN` and `pt-BR`, and a
 * stored language that is no longer offered (Arabic) is cleared, so the person
 * gets English until they choose again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'locale')) {
            return;
        }
        DB::table('users')->where('locale', 'zh')->update(['locale' => 'zh-CN']);
        DB::table('users')->where('locale', 'pt')->update(['locale' => 'pt-BR']);
        DB::table('users')->whereNotNull('locale')->whereNotIn('locale', ['en', 'zh-CN', 'de', 'id', 'ja', 'pt-BR', 'es', 'fr', 'ru'])->update(['locale' => null]);
    }

    public function down(): void
    {
        // The old short codes are not restored: both spellings mean the same language.
    }
};
