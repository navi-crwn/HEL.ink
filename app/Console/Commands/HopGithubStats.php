<?php

namespace App\Console\Commands;

use App\Services\GithubStatsFetcher;
use Illuminate\Console\Command;

class HopGithubStats extends Command
{
    protected $signature = 'hop:github-stats';

    protected $description = 'Fetch repo statistics from GitHub for the home page and keep the last good numbers';

    public function handle(GithubStatsFetcher $fetcher): int
    {
        foreach ($fetcher->refresh() as $repo => $result) {
            $this->line(sprintf('%-22s %s', $repo, $result));
        }

        // A failed or rate-limited run is normal and harmless (the old numbers stay): not an error.
        return self::SUCCESS;
    }
}
