<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Invitee;
use App\Services\ReminderSmsService;
use Illuminate\Console\Command;

class SendRsvpPendingReminderSms extends Command
{
    protected $signature = 'sms:send-rsvp-pending-reminders
                            {--force : Ignore the configured reminder time and run immediately for testing}';

    protected $description = 'Send RSVP-pending reminder SMS at each event configured reminder time.';

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
            ->where('auto_rsvp_pending_reminder_enabled', true)
            ->whereDate('event_date', '>=', $today)
            ->get();

        $totalEvents = 0;
        $totalSent = 0;
        $totalFailed = 0;
        $totalSkipped = 0;

        foreach ($events as $event) {
            if (! $force && ! $event->isRsvpPendingReminderDue($currentTime)) {
                continue;
            }

            $totalEvents++;

            $invitees = Invitee::query()
                ->where('event_id', $event->id)
                ->where('rsvp_status', Invitee::RSVP_STATUS_PENDING)
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
                    "Event {$event->title}: no eligible RSVP-pending invitees found."
                );

                continue;
            }

            $result = $reminderSmsService->sendBulkRsvpPendingReminders($invitees);

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
                    ? 'No RSVP-pending reminder events are eligible.'
                    : "No RSVP-pending reminders are due at {$currentTime}."
            );

            return self::SUCCESS;
        }

        $this->info(
            "RSVP-pending reminders completed. Events: {$totalEvents}, Sent: {$totalSent}, Failed: {$totalFailed}, Skipped: {$totalSkipped}."
        );

        return self::SUCCESS;
    }
}
