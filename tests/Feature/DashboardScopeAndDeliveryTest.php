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
                    'cards' => $this->cardSummary(),
                ];
            }
        };
        $this->assertSame(1, $page->metrics()['invitees']);
        $this->assertSame(1, $page->metrics()['pending']);
        $this->assertSame(['generated' => 0, 'not_generated' => 1, 'types' => [['name' => 'Single', 'count' => 1]]], $page->metrics()['cards']);
        $this->assertSame(['read' => 1], $page->metrics()['delivery']['whatsapp']);
        $page->selectedEventId = $denied->id;
        $this->assertSame(0, $page->metrics()['invitees']);
        $this->assertSame(['generated' => 0, 'not_generated' => 0, 'types' => []], $page->metrics()['cards']);
        $this->assertSame([], $page->metrics()['delivery']['whatsapp']);
        $page->selectedEventId = $allowed->id;
        $this->assertSame(['read' => 1], $page->metrics()['delivery']['whatsapp']);
    }

    public function test_balance_component_denies_event_manager_access(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_EVENT_ADMIN]));
        \Livewire\Livewire::test(\App\Livewire\DashboardSmsBalance::class)->assertForbidden();
    }

    public function test_balance_component_reads_provider_credit_not_response_code(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));
        \Illuminate\Support\Facades\Cache::forget('elive.dashboard.sms_balance');
        config(['services.sms_balance.url' => 'https://sms.example.test/balance',
            'services.sms_balance.api_key' => 'test-key', 'services.sms_balance.api_secret' => 'test-secret']);
        \Illuminate\Support\Facades\Http::fake([
            'https://sms.example.test/*' => \Illuminate\Support\Facades\Http::response(['code' => 200, 'data' => ['totalSms' => 4520]]),
        ]);
        \Livewire\Livewire::test(\App\Livewire\DashboardSmsBalance::class)->assertSee('4,520');
    }
}
