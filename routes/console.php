<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Work Summary Calculations
|--------------------------------------------------------------------------
| Pre-calculate summaries to avoid slow API responses.
| Run: php artisan schedule:run (or add to crontab)
|
*/

// Daily summaries - run at 01:00 every day (calculates yesterday's data)
Schedule::command('summaries:calculate --period=daily')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/summaries.log'));

// Weekly summaries - run at 02:00 every Monday
Schedule::command('summaries:calculate --period=weekly')
    ->weeklyOn(1, '02:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/summaries.log'));

// Monthly summaries - run at 03:00 on the 1st of each month
Schedule::command('summaries:calculate --period=monthly')
    ->monthlyOn(1, '03:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/summaries.log'));

// Dirty summaries - run every 15 minutes to recalculate changed data
Schedule::command('summaries:calculate --dirty-only')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/summaries.log'));
