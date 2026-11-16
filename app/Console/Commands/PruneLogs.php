<?php

namespace App\Console\Commands;

use App\Models\FeedbackReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PruneLogs extends Command
{
    protected $signature = 'hop:prune-logs {--days=90 : Delete records older than this many days}';

    protected $description = 'Delete account activity and sign-in records older than the retention period (90 days by default), and apply the Feedback report schedule (IP 30 days, finished 180 days, all 365 days)';

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
        $this->pruneFeedback();

        return self::SUCCESS;
    }

    /**
     * Feedback reports: the IP address goes after 30 days, a finished report 180 days after it was finished, any report
     * after 365 days. Rows are deleted through the model, so every image file goes with its row.
     */
    private function pruneFeedback(): void
    {
        if (! Schema::hasTable('feedback_reports')) {
            return;
        }
        $ips = DB::table('feedback_reports')->whereNotNull('ip_address')
            ->where('created_at', '<', now()->subDays(FeedbackReport::IP_DAYS))->update(['ip_address' => null]);
        $deleted = 0;
        foreach ([
            FeedbackReport::where('status', 'done')->where('done_at', '<', now()->subDays(FeedbackReport::DONE_DAYS)),
            FeedbackReport::where('created_at', '<', now()->subDays(FeedbackReport::MAX_DAYS)),
        ] as $query) {
            $query->chunkById(200, function ($reports) use (&$deleted) {
                foreach ($reports as $report) {
                    $report->delete();
                    $deleted++;
                }
            });
        }
        $this->line(sprintf('%-16s %d addresses emptied, %d deleted', 'feedback_reports', $ips, $deleted));
    }
}
