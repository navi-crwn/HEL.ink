<?php

use App\Jobs\QueueHeartbeat;
use Illuminate\Support\Facades\Schedule;

// The watchdog: every app's /up each minute, alerts after three failures in a row.
Schedule::command('hop:watch')->everyMinute()->withoutOverlapping()->runInBackground();
Schedule::job(new QueueHeartbeat)->everyTwoMinutes();
// Laravel housekeeping on the shared tables the hub owns.
Schedule::command('auth:clear-resets')->daily();
Schedule::command('disposable:update')->weeklyOn(1, '03:00');
