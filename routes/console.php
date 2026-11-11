<?php

use App\Jobs\QueueHeartbeat;
use Illuminate\Support\Facades\Schedule;

// The watchdog: every app's /up each minute, alerts after three failures in a row.
Schedule::command('hop:watch')->everyMinute()->withoutOverlapping()->runInBackground();
Schedule::job(new QueueHeartbeat)->everyTwoMinutes();
// Laravel housekeeping on the shared tables the hub owns.
Schedule::command('auth:clear-resets')->daily();
// Activity and sign-in records are deleted after 90 days (the privacy page says so).
Schedule::command('hop:prune-logs')->daily()->withoutOverlapping();
Schedule::command('disposable:update')->weeklyOn(1, '03:00');
Schedule::command('hop:email-blocklist:refresh')->weeklyOn(1, '03:10');
// Repo numbers for the home page, fetched on the server (ETag: unchanged answers cost no quota).
Schedule::command('hop:github-stats')->hourly()->withoutOverlapping()->runInBackground();
