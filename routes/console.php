<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Force submit expired exam attempts every minute
Schedule::command('exam:force-submit-expired')->everyMinute();

// Persist answers from Redis to MySQL every minute
Schedule::job(new \App\Jobs\PersistAnswersJob)->everyMinute();

// Send deadline reminders daily at 07:00
Schedule::command('notifications:send-deadline-reminders')->dailyAt('07:00');

// Daily database backup at 02:00
Schedule::command('backup:database')->dailyAt('02:00');

// Daily audit log cleanup at 03:00
Schedule::command('audit:cleanup')->dailyAt('03:00');
