<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\AuditLogService;
use Illuminate\Console\Command;

class RepairPrematureEventCompletion extends Command
{
    protected $signature = 'events:repair-premature-completion
        {--event= : Event ID to inspect (required for applying changes)}
        {--apply : Reopen a prematurely completed event after operator review}';

    protected $description = 'Inspect events marked completed before their scheduled end; optionally restore one event.';

    public function handle(): int
    {
        $eventId = $this->option('event');
        $apply = (bool) $this->option('apply');

        if ($eventId !== null && (! ctype_digit((string) $eventId) || (int) $eventId < 1)) {
            $this->error('Provide a positive numeric event ID.');
            return self::FAILURE;
        }

        if ($apply && $eventId === null) {
            $this->error('Applying a repair requires --event=ID to protect intentional early completions.');
            return self::FAILURE;
        }

        $query = Event::query()->where('status', Event::STATUS_COMPLETED);

        if ($eventId !== null) {
            $query->whereKey((int) $eventId);
        }

        $found = 0;
        $repaired = 0;

        $query->orderBy('id')->chunkById(100, function ($events) use ($apply, &$found, &$repaired): void {
            foreach ($events as $event) {
                // Do not touch events that already ended or have no reliable end date.
                if (! $event->ends_at || ! now()->lessThan($event->ends_at)) {
                    continue;
                }

                $found++;

                $this->warn(sprintf(
                    'Event %d: %s, stored=completed, scheduled_end=%s, now=%s',
                    $event->getKey(),
                    $event->title,
                    $event->ends_at->toDateTimeString(),
                    now()->toDateTimeString(),
                ));

                if (! $apply) {
                    continue;
                }

                $event->update(['status' => Event::STATUS_ACTIVE]);
                $repaired++;

                AuditLogService::record(
                    action: 'event.premature_completion_repaired',
                    subject: $event,
                    eventId: $event->getKey(),
                    description: 'Stored completed status was reset after operator-initiated review of event end time.',
                    oldValues: ['status' => Event::STATUS_COMPLETED],
                    newValues: ['status' => Event::STATUS_ACTIVE],
                    metadata: [
                        'source' => 'artisan_events_repair_premature_completion',
                        'scheduled_end' => $event->ends_at->toDateTimeString(),
                        'reason' => 'operator_requested_repair',
                    ],
                );
            }
        });

        $this->info("Prematurely completed events found: {$found}; repaired: {$repaired}.");

        if (! $apply) {
            $this->line('Dry run only. Review event history before running with --event=ID --apply.');
        }

        return self::SUCCESS;
    }
}
