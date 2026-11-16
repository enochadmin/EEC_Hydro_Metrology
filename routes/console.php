<?php

use Illuminate\Support\Facades\Schedule;

/**
 * Scheduled work.
 *
 * The schedule is driven by the single system cron entry documented in
 * docs/DEPLOYMENT.md: `* * * * * php artisan schedule:run`.
 */
Schedule::command('eec:backup')
    ->dailyAt(config('backup.schedule.time', '01:30'))
    ->when(fn (): bool => (bool) config('backup.schedule.enabled', true))
    ->withoutOverlapping()
    ->onOneServer();
