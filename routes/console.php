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
| The scheduler runs every minute.
|
| Each reminder command checks:
| - whether automatic reminders are enabled for the event;
| - whether that reminder type is enabled;
| - whether the event date is eligible;
| - whether the event's configured reminder time matches the current time;
| - whether the invitee is eligible;
| - whether the reminder has already been sent.
|
| Local development:
| php artisan schedule:work
|
| Production server cron:
| * * * * * cd /path/to/elive-card && php artisan schedule:run >> /dev/null 2>&1
*/

Schedule::command('sms:send-rsvp-pending-reminders')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->onOneServer();

Schedule::command('sms:send-one-day-before-reminders')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->onOneServer();

Schedule::command('sms:send-event-day-reminders')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->onOneServer();
