<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('wallet:clear-matured-entries')->dailyAt('01:00');
Schedule::command('payouts:run-automatic')->dailyAt('02:00');
Schedule::command('commission:generate-invoices')->monthlyOn(1, '03:00');
Schedule::command('trials:send-reminders')->dailyAt('10:00');

// Automated Push Notification Schedules (Asia/Kolkata)
Schedule::command('notifications:match-reminders')->everyFiveMinutes();
Schedule::command('notifications:review-prompts')->everyFifteenMinutes();
Schedule::command('notifications:venue-daily-digest')->dailyAt('08:00')->timezone('Asia/Kolkata');
