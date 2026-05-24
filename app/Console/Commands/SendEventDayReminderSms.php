<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Invitee;
use App\Services\ReminderSmsService;
use Illuminate\Console\Command;

class SendEventDayReminderSms extends Command
{
    protected $signature = 'sms:send-event-day-reminders';

    protected $description = 'Send event day final reminder SMS to invitees for events with automatic reminders enabled.';

    public function handle(ReminderSmsService $reminderSmsService): int
    {
        $today = now()->toDateString();

        $events = Event::query()
            ->whereIn('status', [
                Event::STATUS_DRAFT,
                Event::STATUS_ACTIVE,
            ])
            ->where('auto_sms_reminders_enabled', true)
            ->where('auto_event_day_reminder_enabled', true)
            ->whereDate('event_date', $today)
            ->get();

        $totalSent = 0;
        $totalFailed = 0;
        $totalSkipped = 0;

        foreach ($events as $event) {
            $invitees = Invitee::query()
                ->where('event_id', $event->id)
                ->whereIn('rsvp_status', [
                    Invitee::RSVP_STATUS_PENDING,
                    Invitee::RSVP_STATUS_ATTENDING,
                ])
                ->where(function ($query) {
                    $query
                        ->whereNull('final_sms_status')
                        ->orWhere('final_sms_status', '!=', Invitee::SMS_STATUS_SENT);
                })
                ->whereNotNull('phone')
                ->get();

            if ($invitees->isEmpty()) {
                $this->line("Event {$event->title}: no invitees found for event day reminder.");
                continue;
            }

            $result = $reminderSmsService->sendBulkEventDayReminders($invitees);

            $totalSent += $result['sent'];
            $totalFailed += $result['failed'];
            $totalSkipped += $result['skipped'];

            $this->line("Event {$event->title}: Sent {$result['sent']}, Failed {$result['failed']}, Skipped {$result['skipped']}.");
        }

        $this->info("Event day reminders completed. Sent: {$totalSent}, Failed: {$totalFailed}, Skipped: {$totalSkipped}");

        return self::SUCCESS;
    }
}