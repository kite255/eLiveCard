<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\AuditLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CompleteFinishedEvents extends Command
{
    protected $signature = 'events:complete-finished';

    protected $description = 'Mark active events as completed after their end date and time have passed.';

    public function handle(): int
    {
        $completedCount = 0;

        Event::query()
            ->where('status', Event::STATUS_ACTIVE)
            ->whereNotNull('event_date')
            ->orderBy('id')
            ->chunkById(100, function ($events) use (&$completedCount): void {
                foreach ($events as $event) {
                    $endsAt = $this->resolveEventEndDateTime($event);

                    if (! $endsAt || $endsAt->isFuture()) {
                        continue;
                    }

                    $event->update([
                        'status' => Event::STATUS_COMPLETED,
                    ]);

                    AuditLogService::record(
                        action: 'event.automatically_completed',
                        subject: $event,
                        eventId: $event->getKey(),
                        description: 'Event completed by the scheduled end-time command.',
                        oldValues: ['status' => Event::STATUS_ACTIVE],
                        newValues: ['status' => Event::STATUS_COMPLETED],
                        metadata: [
                            'source' => 'artisan_events_complete_finished',
                            'scheduled_end' => $endsAt->toDateTimeString(),
                        ],
                    );

                    $completedCount++;

                    Log::info('Event automatically marked as completed.', [
                        'event_id' => $event->getKey(),
                        'event_title' => $event->title,
                        'ended_at' => $endsAt->toDateTimeString(),
                    ]);
                }
            });

        if ($completedCount === 0) {
            $this->info('No finished active events were found.');

            return self::SUCCESS;
        }

        $this->info(
            "{$completedCount} finished event(s) marked as completed."
        );

        return self::SUCCESS;
    }

    private function resolveEventEndDateTime(Event $event): ?\Illuminate\Support\Carbon
    {
        if (! $event->event_date) {
            return null;
        }

        // Share the overnight end-time rule with the Event display status.
        if ($event->end_time) {
            return $event->ends_at;
        }

        $endsAt = $event->starts_at;

        return $event->start_time
            ? $endsAt?->addHours(6)
            : $event->event_date->copy()->endOfDay();
    }
}
