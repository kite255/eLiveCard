<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| eLive Card Automatic Reminder SMS Schedule
|--------------------------------------------------------------------------
| These commands run automatically when Laravel scheduler is active.
|
| Local development:
| php artisan schedule:work
|
| Production server cron:
| * * * * * cd /path/to/elive-card && php artisan schedule:run >> /dev/null 2>&1
*/

Schedule::command('sms:send-rsvp-pending-reminders')
    ->dailyAt('09:00')
    ->withoutOverlapping();

Schedule::command('sms:send-one-day-before-reminders')
    ->dailyAt('10:00')
    ->withoutOverlapping();

Schedule::command('sms:send-event-day-reminders')
    ->dailyAt('06:00')
    ->withoutOverlapping();