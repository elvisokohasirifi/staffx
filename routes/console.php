<?php

use App\Jobs\RunDatabaseBackupJob;
use App\Jobs\SendPendingTaskRemindersJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new RunDatabaseBackupJob)
    ->name('daily-database-backup')
    ->timezone(config('app.timezone'))
    ->dailyAt('01:00')
    ->withoutOverlapping();

Schedule::job(new SendPendingTaskRemindersJob)
    ->name('pending-task-reminders-3pm')
    ->timezone(config('app.timezone'))
    ->dailyAt('15:00')
    ->withoutOverlapping();

Schedule::job(new SendPendingTaskRemindersJob)
    ->name('pending-task-reminders-8pm')
    ->timezone(config('app.timezone'))
    ->dailyAt('20:00')
    ->withoutOverlapping();
