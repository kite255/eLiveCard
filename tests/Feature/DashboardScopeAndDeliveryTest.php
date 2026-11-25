<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\MessageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardScopeAndDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_scope_and_latest_invitation_receipts(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_EVENT_ADMIN]);
        $other = User::factory()->create(['role' => User::ROLE_EVENT_ADMIN]);
        $this->actingAs($owner);
        $makeEvent = fn ($user, $title) => Event::query()->create([
            'user_id' => $user->id, 'title' => $title,
            'event_type' => Event::TYPE_WEDDING, 'status' => Event::STATUS_ACTIVE,
        ]);
        $allowed = $makeEvent($owner, 'Allowed');
        $denied = $makeEvent($other, 'Denied');
        foreach ([$allowed, $denied] as $event) {
            $type = \App\Models\CardType::query()->create([
                'event_id' => $event->id, 'name' => 'Single',
                'allowed_people' => 1, 'is_active' => true,
            ]);
            $invitee = Invitee::query()->create([
                'event_id' => $event->id, 'card_type_id' => $type->id,
                'name' => 'Guest', 'phone' => '255700000999', 'rsvp_status' => 'maybe',
            ]);
            foreach ([['invitation', 'sent'], ['invitation', 'read'], ['reminder', 'failed']] as [$type, $status]) {
                MessageLog::query()->create([
                    'event_id' => $event->id, 'invitee_id' => $invitee->id,
                    'channel' => 'whatsapp', 'type' => $type, 'status' => $status,
                ]);
            }
        }
        $page = new class extends Dashboard {
            public function metrics(): array
            {
                return [
                    'invitees' => $this->countInvitees(),
                    'pending' => $this->countPendingRsvpInvitees(),
                    'delivery' => $this->invitationDeliveryCounts(),
                ];
            }
        };
        $this->assertSame(1, $page->metrics()['invitees']);
        $this->assertSame(1, $page->metrics()['pending']);
        $this->assertSame(['read' => 1], $page->metrics()['delivery']['whatsapp']);
        $page->selectedEventId = $denied->id;
        $this->assertSame(0, $page->metrics()['invitees']);
        $this->assertSame([], $page->metrics()['delivery']['whatsapp']);
        $page->selectedEventId = $allowed->id;
        $this->assertSame(['read' => 1], $page->metrics()['delivery']['whatsapp']);
    }
}
