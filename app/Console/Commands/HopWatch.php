<?php

namespace App\Console\Commands;

use App\Services\Watchdog;
use Illuminate\Console\Command;

class HopWatch extends Command
{
    protected $signature = 'hop:watch';

    protected $description = 'Probe every HOP app (/up and its functional check) and record the result in service_checks';

    public function handle(Watchdog $watchdog): int
    {
        $down = 0;
        foreach ($watchdog->run() as $slug => $kinds) {
            foreach ($kinds as $kind => $ok) {
                $this->line(sprintf('%-10s %-7s %s', $slug, $kind, $ok ? '<info>ok</info>' : '<error>FAIL</error>'));
                $down += $ok ? 0 : 1;
            }
        }

        return $down ? self::FAILURE : self::SUCCESS;
    }
}
