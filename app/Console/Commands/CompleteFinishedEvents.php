<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
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

    private function resolveEventEndDateTime(Event $event): ?Carbon
    {
        if (! $event->event_date) {
            return null;
        }

        $endsAt = $event->event_date->copy();

        if ($event->end_time) {
            $endsAt->setTime(
                $event->end_time->hour,
                $event->end_time->minute,
                $event->end_time->second
            );

            return $endsAt;
        }

        if ($event->start_time) {
            $endsAt->setTime(
                $event->start_time->hour,
                $event->start_time->minute,
                $event->start_time->second
            );

            return $endsAt->addHours(6);
        }

        return $endsAt->endOfDay();
    }
}
