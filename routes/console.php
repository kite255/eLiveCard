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
|
| Each reminder command performs its own eligibility checks before sending.
|
| Local development:
| php artisan schedule:work
|
| Production cron:
| * * * * * cd /path/to/elive-card && php artisan schedule:run >> /dev/null 2>&1
|
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

/*
|--------------------------------------------------------------------------
| SMS Delivery Report Synchronization
|--------------------------------------------------------------------------
|
| Checks eligible SMS every minute (maximum 25 API lookups per run).
| Retry intervals: 1 minute for the first 15 minutes, 5 minutes for the
| remainder of the first hour, then 30 minutes until a final receipt arrives.
| Confirmed Delivered/Failed messages are excluded by the command.
| Provider API capacity and scheduler runtime should be monitored.
|
| The command updates fields such as:
| - status
| - provider_status
| - delivered_at
| - failed_at
| - error_message
|
*/

Schedule::command('sms:sync-delivery-reports --limit=25')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->onOneServer();

/*
|--------------------------------------------------------------------------
| Automatic Event Completion
|--------------------------------------------------------------------------
|
| Active events are changed to completed after their configured end time.
|
| Completion rules:
| - use the event end time when available;
| - otherwise use six hours after the start time;
| - otherwise use the end of the event date.
|
| Draft and cancelled events are never changed automatically.
|
*/

Schedule::command('events:complete-finished')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->onOneServer();

// Opt-in post-event thank-you SMS; unique delivery records prevent re-sending.
Schedule::command('sms:send-automatic-thank-you')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->onOneServer();
