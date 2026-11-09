<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GET /api/hop/apps used to answer for LinkHop with a capability list written in the
 * controller (`admin_api`, `metrics`), whatever the registry row said. The controller now
 * reports the row, so an existing LinkHop row gets those two capabilities once, here:
 * Imperium keeps seeing exactly what it saw before. Nothing else in the row changes, a
 * capability the row already has stays, and a second run finds nothing to add.
 */
return new class extends Migration
{
    private const CARRIED = ['admin_api', 'metrics'];

    public function up(): void
    {
        if (! Schema::hasTable('apps') || ! Schema::hasColumn('apps', 'capabilities')) {
            return;
        }
        $row = DB::table('apps')->where('slug', 'linkhop')->first(['id', 'capabilities']);
        if (! $row) {
            return;
        }
        $current = json_decode((string) ($row->capabilities ?? '[]'), true);
        $current = is_array($current) ? array_values(array_filter($current, 'is_string')) : [];
        $merged = array_values(array_unique(array_merge($current, self::CARRIED)));
        if ($merged !== $current) {
            DB::table('apps')->where('id', $row->id)->update(['capabilities' => json_encode($merged)]);
        }
    }

    public function down(): void
    {
        // Not undone: the row cannot tell which capabilities it had before.
    }
};
