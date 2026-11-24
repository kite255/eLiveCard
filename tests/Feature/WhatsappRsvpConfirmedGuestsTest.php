<?php

namespace Tests\Feature;

use App\Models\CardType;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\User;
use App\Services\RsvpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsappRsvpConfirmedGuestsTest extends TestCase
{
    use RefreshDatabase;

    private function makeInvitee(int $guestCount): Invitee
    {
        $event = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'WhatsApp Guest Confirmation Test',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);
        $type = CardType::query()->create([
            'event_id' => $event->id,
            'name' => $guestCount === 2 ? 'Double' : 'Single',
            'allowed_people' => $guestCount,
            'is_active' => true,
        ]);
        return Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $type->id,
            'name' => 'RSVP Test Guest',
            'phone' => '255700000010',
            'allowed_guests' => $guestCount,
            'confirmed_guests' => 0,
        ]);
    }

    public function test_whatsapp_attending_confirms_single_invitation_guest(): void
    {
        $invitee = $this->makeInvitee(1);
        $updated = app(RsvpService::class)
            ->updateFromWhatsappButton($invitee, 'rsvp_attending', 'Attending');

        $this->assertSame(Invitee::RSVP_STATUS_ATTENDING, $updated->fresh()->rsvp_status);
        $this->assertSame(1, (int) $updated->fresh()->confirmed_guests);
    }

    public function test_whatsapp_attending_confirms_double_invitation_guest_count(): void
    {
        $invitee = $this->makeInvitee(2);
        $updated = app(RsvpService::class)
            ->updateFromWhatsappButton($invitee, 'rsvp_attending', 'Attending');

        $this->assertSame(2, (int) $updated->fresh()->confirmed_guests);
    }

    public function test_whatsapp_decline_resets_existing_guest_count(): void
    {
        $invitee = $this->makeInvitee(2);
        $service = app(RsvpService::class);

        $service->confirmAttendance($invitee);
        $service->updateFromWhatsappButton($invitee, 'rsvp_not_attending', 'Cannot attend');

        $this->assertSame(Invitee::RSVP_STATUS_NOT_ATTENDING, $invitee->fresh()->rsvp_status);
        $this->assertSame(0, (int) $invitee->fresh()->confirmed_guests);
    }

    public function test_repeated_whatsapp_confirmation_is_idempotent_for_guest_total(): void
    {
        $invitee = $this->makeInvitee(2);
        $service = app(RsvpService::class);
        $service->confirmAttendance($invitee);
        $service->confirmAttendance($invitee->fresh());

        $this->assertSame(2, (int) $invitee->fresh()->confirmed_guests);
    }
}
