<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Invitee;
use App\Services\ReminderSmsService;
use Illuminate\Console\Command;

class SendOneDayBeforeReminderSms extends Command
{
    protected $signature = 'sms:send-one-day-before-reminders';

    protected $description = 'Send one-day-before reminder SMS to attending invitees for events with automatic reminders enabled.';

    public function handle(ReminderSmsService $reminderSmsService): int
    {
        $targetDate = now()->addDay()->toDateString();

        $events = Event::query()
            ->whereIn('status', [
                Event::STATUS_DRAFT,
                Event::STATUS_ACTIVE,
            ])
            ->where('auto_sms_reminders_enabled', true)
            ->where('auto_one_day_reminder_enabled', true)
            ->whereDate('event_date', $targetDate)
            ->get();

        $totalSent = 0;
        $totalFailed = 0;
        $totalSkipped = 0;

        foreach ($events as $event) {
            $invitees = Invitee::query()
                ->where('event_id', $event->id)
                ->where('rsvp_status', Invitee::RSVP_STATUS_ATTENDING)
                ->where(function ($query) {
                    $query
                        ->whereNull('reminder_sms_status')
                        ->orWhere('reminder_sms_status', '!=', Invitee::SMS_STATUS_SENT);
                })
                ->whereNotNull('phone')
                ->get();

            if ($invitees->isEmpty()) {
                $this->line("Event {$event->title}: no attending invitees found for one-day-before reminder.");
                continue;
            }

            $result = $reminderSmsService->sendBulkAttendingReminders($invitees);

            $totalSent += $result['sent'];
            $totalFailed += $result['failed'];
            $totalSkipped += $result['skipped'];

            $this->line("Event {$event->title}: Sent {$result['sent']}, Failed {$result['failed']}, Skipped {$result['skipped']}.");
        }

        $this->info("One-day-before reminders completed. Sent: {$totalSent}, Failed: {$totalFailed}, Skipped: {$totalSkipped}");

        return self::SUCCESS;
    }
}