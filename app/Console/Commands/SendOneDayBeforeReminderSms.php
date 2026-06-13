<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Invitee;
use App\Services\ReminderSmsService;
use Illuminate\Console\Command;

class SendOneDayBeforeReminderSms extends Command
{
    protected $signature = 'sms:send-one-day-before-reminders
                            {--force : Ignore the configured reminder time and run immediately for testing}';

    protected $description = 'Send one-day-before reminder SMS at each event configured reminder time.';

    public function handle(ReminderSmsService $reminderSmsService): int
    {
        $targetDate = now()->addDay()->toDateString();
        $currentTime = now()->format('H:i');
        $force = (bool) $this->option('force');

        $events = Event::query()
            ->whereIn('status', [
                Event::STATUS_DRAFT,
                Event::STATUS_ACTIVE,
            ])
            ->where('auto_sms_reminders_enabled', true)
            ->where('auto_one_day_reminder_enabled', true)
            ->whereDate('event_date', $targetDate)
            ->get();

        $totalEvents = 0;
        $totalSent = 0;
        $totalFailed = 0;
        $totalSkipped = 0;

        foreach ($events as $event) {
            if (! $force && ! $event->isOneDayReminderDue($currentTime)) {
                continue;
            }

            $totalEvents++;

            $invitees = Invitee::query()
                ->where('event_id', $event->id)
                ->where('rsvp_status', Invitee::RSVP_STATUS_ATTENDING)
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->where(function ($query) {
                    $query
                        ->whereNull('reminder_sms_status')
                        ->orWhere('reminder_sms_status', '!=', Invitee::SMS_STATUS_SENT);
                })
                ->get();

            if ($invitees->isEmpty()) {
                $this->line(
                    "Event {$event->title}: no eligible attending invitees found."
                );

                continue;
            }

            $result = $reminderSmsService->sendBulkAttendingReminders($invitees);

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
                    ? 'No one-day-before events are eligible for tomorrow.'
                    : "No one-day-before reminders are due at {$currentTime}."
            );

            return self::SUCCESS;
        }

        $this->info(
            "One-day-before reminders completed. Events: {$totalEvents}, Sent: {$totalSent}, Failed: {$totalFailed}, Skipped: {$totalSkipped}."
        );

        return self::SUCCESS;
    }
}
