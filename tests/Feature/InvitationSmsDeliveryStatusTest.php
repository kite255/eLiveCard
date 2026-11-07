<?php

namespace Tests\Feature;

use App\Models\CardType;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\InvitationSmsDeliveryStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationSmsDeliveryStatusTest extends TestCase
{
    use RefreshDatabase;

    private function invitee(): Invitee
    {
        $event = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Delivery Status Event',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);
        $card = CardType::query()->create([
            'event_id' => $event->id,
            'name' => 'Single',
            'allowed_guests' => 1,
        ]);
        return Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $card->id,
            'name' => 'SMS Guest',
            'phone' => '255700000010',
            'card_status' => Invitee::CARD_STATUS_ACTIVE,
        ]);
    }

    private function log(Invitee $invitee, string $status): SmsLog
    {
        return SmsLog::query()->create([
            'event_id' => $invitee->event_id,
            'invitee_id' => $invitee->id,
            'phone' => $invitee->phone,
            'sms_type' => SmsLog::TYPE_INVITATION,
            'message' => 'Private invitation link',
            'status' => $status,
            'provider' => 'eLive SMS',
            'provider_message_id' => 'shoot-id-123',
            'provider_status' => $status,
        ]);
    }

    public function test_legacy_invitee_sent_status_without_log_is_unverified(): void
    {
        $invitee = $this->invitee();
        $invitee->forceFill([
            'sms_status' => 'sent',
            'invitation_sms_status' => 'sent',
            'last_message_channel' => 'sms',
            'last_message_status' => 'sent',
        ])->save();

        $state = app(InvitationSmsDeliveryStatusService::class)->forInvitee($invitee->fresh());
        $this->assertSame('Unverified', $state['label']);
        $this->assertNull($state['log']);
    }

    public function test_submitted_sms_is_not_mislabeled_as_delivered(): void
    {
        $invitee = $this->invitee();
        $this->log($invitee, 'sent');
        $state = app(InvitationSmsDeliveryStatusService::class)->forInvitee($invitee);
        $this->assertSame('Submitted', $state['label']);
        $this->assertStringContainsString('does not confirm handset delivery', $state['description']);
    }

    public function test_provider_delivered_and_failed_states_are_displayed(): void
    {
        $invitee = $this->invitee();
        $log = $this->log($invitee, 'delivered');
        $state = app(InvitationSmsDeliveryStatusService::class)->forInvitee($invitee);
        $this->assertSame('Delivered', $state['label']);

        $log->update(['status' => 'failed', 'provider_status' => 'undelivered']);
        $state = app(InvitationSmsDeliveryStatusService::class)->forInvitee($invitee);
        $this->assertSame('Failed', $state['label']);
        $this->assertStringContainsString('undelivered', $state['description']);
    }

    public function test_force_resend_queue_state_takes_precedence_over_older_sent_log(): void
    {
        $invitee = $this->invitee();
        $this->log($invitee, 'sent');
        $invitee->forceFill([
            'last_message_channel' => 'sms',
            'last_message_status' => 'queued',
            'sms_status' => 'pending',
        ])->save();

        $state = app(InvitationSmsDeliveryStatusService::class)->forInvitee($invitee->fresh());
        $this->assertSame('Queued', $state['label']);
    }
}
