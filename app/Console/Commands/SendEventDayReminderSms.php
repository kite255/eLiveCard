<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Invitee;
use App\Services\ReminderSmsService;
use Illuminate\Console\Command;

class SendEventDayReminderSms extends Command
{
    protected $signature = 'sms:send-event-day-reminders
                            {--force : Ignore the configured reminder time and run immediately for testing}';

    protected $description = 'Send event-day reminder SMS at each event configured reminder time.';

    public function handle(ReminderSmsService $reminderSmsService): int
    {
        $today = now()->toDateString();
        $currentTime = now()->format('H:i');
        $force = (bool) $this->option('force');

        $events = Event::query()
            ->whereIn('status', [
                Event::STATUS_DRAFT,
                Event::STATUS_ACTIVE,
            ])
            ->where('auto_sms_reminders_enabled', true)
            ->where('auto_event_day_reminder_enabled', true)
            ->whereDate('event_date', $today)
            ->get();

        $totalEvents = 0;
        $totalSent = 0;
        $totalFailed = 0;
        $totalSkipped = 0;

        foreach ($events as $event) {
            if (! $force && ! $event->isEventDayReminderDue($currentTime)) {
                continue;
            }

            $totalEvents++;

            /*
             * Event-day reminders are sent only to invitees who confirmed
             * that they are attending.
             */
            $invitees = Invitee::query()
                ->where('event_id', $event->id)
                ->where('rsvp_status', Invitee::RSVP_STATUS_ATTENDING)
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->where(function ($query) {
                    $query
                        ->whereNull('final_sms_status')
                        ->orWhere('final_sms_status', '!=', Invitee::SMS_STATUS_SENT);
                })
                ->get();

            if ($invitees->isEmpty()) {
                $this->line(
                    "Event {$event->title}: no eligible attending invitees found."
                );

                continue;
            }

            $result = $reminderSmsService->sendBulkEventDayReminders($invitees);

            $sent = (int) ($result['sent'] ?? 0);
            $failed = (int) ($result['failed'] ?? 0);
            $skipped = (int) ($result['skipped'] ?? 0);

            $totalSent += $sent;
            $totalFailed += $failed;
            $totalSkipped += $skipped;

            $this->line(
                "Event {$event->title}: Sent {$sent}, Failed {$failed}, Skipped {$skipped}."
            );
        }

        if ($totalEvents === 0) {
            $this->info(
                $force
                    ? 'No event-day events are eligible today.'
                    : "No event-day reminders are due at {$currentTime}."
            );

            return self::SUCCESS;
        }

        $this->info(
            "Event-day reminders completed. Events: {$totalEvents}, Sent: {$totalSent}, Failed: {$totalFailed}, Skipped: {$totalSkipped}."
        );

        return self::SUCCESS;
    }
}
