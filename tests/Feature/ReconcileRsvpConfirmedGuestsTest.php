<?php

namespace Tests\Feature;

use App\Models\CardType;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconcileRsvpConfirmedGuestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_missing_confirmations_without_updating_rows(): void
    {
        $invitee = $this->fixture();
        $this->artisan('rsvp:reconcile-confirmed-guests', ['event' => $invitee->event_id])
            ->assertExitCode(0);

        $this->assertSame(0, (int) $invitee->fresh()->confirmed_guests);
    }

    public function test_apply_updates_missing_counts_and_preserves_positive_counts_and_other_events(): void
    {
        $first = $this->fixture();
        $second = $this->fixture($first->event_id, 1, 1);
        $other = $this->fixture();

        $this->artisan('rsvp:reconcile-confirmed-guests', [
            'event' => $first->event_id,
            '--apply' => true,
        ])->assertExitCode(0);

        $this->assertSame(2, (int) $first->fresh()->confirmed_guests);
        $this->assertSame(1, (int) $second->fresh()->confirmed_guests);
        $this->assertSame(0, (int) $other->fresh()->confirmed_guests);
    }

    private function fixture(?int $eventId = null, int $guests = 2, int $confirmed = 0): Invitee
    {
        $event = $eventId
            ? Event::query()->findOrFail($eventId)
            : Event::query()->create([
                'user_id' => User::factory()->create()->id,
                'title' => 'Reconcile Test',
                'event_type' => Event::TYPE_WEDDING,
                'status' => Event::STATUS_ACTIVE,
            ]);

        $card = CardType::query()->create([
            'event_id' => $event->id,
            'name' => 'Card '.uniqid(),
            'allowed_people' => $guests,
        ]);

        return Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $card->id,
            'name' => 'Test '.uniqid(),
            'phone' => '255700000010',
            'allowed_guests' => $guests,
            'rsvp_status' => Invitee::RSVP_STATUS_ATTENDING,
            'confirmed_guests' => $confirmed,
        ]);
    }
}
