<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneLogs extends Command
{
    protected $signature = 'hop:prune-logs {--days=90 : Delete records older than this many days}';

    protected $description = 'Delete account activity and sign-in records older than the retention period (90 days by default)';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        foreach (['activity_logs' => 'created_at', 'login_histories' => 'logged_in_at'] as $table => $column) {
            $deleted = 0;
            // Small batches keep each DELETE short on a busy table (and work on SQLite, which has no DELETE ... LIMIT).
            while (($ids = DB::table($table)->where($column, '<', $cutoff)->orderBy('id')->limit(500)->pluck('id'))->isNotEmpty()) {
                $deleted += DB::table($table)->whereIn('id', $ids)->delete();
            }
            $this->line(sprintf('%-16s %d deleted', $table, $deleted));
        }

        return self::SUCCESS;
    }
}
