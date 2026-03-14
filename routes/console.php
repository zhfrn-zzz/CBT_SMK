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
