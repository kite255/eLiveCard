<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Invitee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileRsvpConfirmedGuests extends Command
{
    protected $signature = 'rsvp:reconcile-confirmed-guests
                            {event : Event ID to inspect}
                            {--apply : Update attending invitees with zero or missing confirmed guest counts}';

    protected $description = 'Preview or reconcile missing confirmed guest counts using each attending invitation\'s allowed guest count.';

    public function handle(): int
    {
        $eventId = filter_var($this->argument('event'), FILTER_VALIDATE_INT);
        if (! $eventId || $eventId < 1 || ! Event::query()->whereKey($eventId)->exists()) {
            $this->error('Provide an existing numeric event ID.');
            return self::FAILURE;
        }

        // Attendance must be explicitly recorded; never modify pending or declined invites.
        $query = Invitee::query()
            ->where('event_id', $eventId)
            ->where('rsvp_status', Invitee::RSVP_STATUS_ATTENDING)
            ->where(function ($query) {
                $query->whereNull('confirmed_guests')
                    ->orWhere('confirmed_guests', '<=', 0);
            });

        $invitees = $query->with('cardType')->orderBy('id')->get();
        $predictedTotal = $invitees->sum(
            fn (Invitee $invitee): int => max(1, (int) $invitee->final_allowed_guests)
        );
        $existingTotal = (int) Invitee::query()
            ->where('event_id', $eventId)
            ->where('rsvp_status', Invitee::RSVP_STATUS_ATTENDING)
            ->sum('confirmed_guests');

        $this->table(
            ['Event', 'Missing counts', 'Current confirmed', 'Proposed addition', 'Projected total'],
            [[$eventId, $invitees->count(), $existingTotal, $predictedTotal, $existingTotal + $predictedTotal]]
        );

        foreach ($invitees as $invitee) {
            $this->line(sprintf(
                'Invitee #%d: %d guest(s) (allowed invitation capacity)',
                $invitee->id,
                max(1, (int) $invitee->final_allowed_guests)
            ));
        }

        if (! $this->option('apply')) {
            $this->info('Dry run only. No database values were changed. Use --apply only after verifying the invitation-count policy.');
            return self::SUCCESS;
        }

        // Guard the update so a concurrent RSVP reply or prior correction is not overwritten.
        $updated = 0;
        DB::transaction(function () use ($invitees, $eventId, &$updated): void {
            foreach ($invitees as $invitee) {
                $count = max(1, (int) $invitee->final_allowed_guests);
                $updated += DB::table('invitees')
                    ->where('id', $invitee->id)
                    ->where('event_id', $eventId)
                    ->where('rsvp_status', Invitee::RSVP_STATUS_ATTENDING)
                    ->where(function ($query): void {
                        $query->whereNull('confirmed_guests')
                            ->orWhere('confirmed_guests', '<=', 0);
                    })
                    ->update(['confirmed_guests' => $count, 'updated_at' => now()]);
            }
        });

        $this->info("Updated {$updated} attending invitee records. Existing positive counts were preserved.");

        return self::SUCCESS;
    }
}
