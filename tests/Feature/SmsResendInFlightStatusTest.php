<?php

namespace Tests\Feature;

use App\Models\CardType;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\InviteeInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmsResendInFlightStatusTest extends TestCase
{
    use RefreshDatabase;

    private function invitee(): Invitee
    {
        $event = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'SMS Resend Test',
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
            'name' => 'Resend Recipient',
            'phone' => '255700000010',
            'card_status' => Invitee::CARD_STATUS_ACTIVE,
        ]);
    }

    private function log(Invitee $invitee, string $status, string $messageId): void
    {
        SmsLog::query()->create([
            'event_id' => $invitee->event_id,
            'invitee_id' => $invitee->id,
            'phone' => $invitee->phone,
            'sms_type' => SmsLog::TYPE_INVITATION,
            'message' => 'Test invitation message',
            'status' => $status,
            'provider' => 'eLive SMS',
            'provider_message_id' => $messageId,
        ]);
    }

    private function isInFlight(Invitee $invitee): bool
    {
        return (bool) (new \ReflectionMethod(InviteeInvitationService::class, 'smsInvitationInFlight'))
            ->invoke(app(InviteeInvitationService::class), $invitee->fresh());
    }

    public function test_previous_pending_log_does_not_block_when_newest_sms_was_delivered(): void
    {
        $invitee = $this->invitee();
        $this->log($invitee, 'queued', 'old-queued');
        $this->log($invitee, 'delivered', 'new-delivered');
        $invitee->forceFill([
            'last_message_channel' => 'sms',
            'last_message_status' => 'sending',
            'invitation_sms_status' => 'queued',
        ])->saveQuietly();

        $this->assertFalse($this->isInFlight($invitee));
    }

    public function test_latest_pending_sms_still_blocks_resend(): void
    {
        $invitee = $this->invitee();
        $this->log($invitee, 'delivered', 'old-delivered');
        $this->log($invitee, 'queued', 'latest-queued');

        $this->assertTrue($this->isInFlight($invitee));
    }

    public function test_queued_invitation_without_log_still_blocks_resend(): void
    {
        $invitee = $this->invitee();
        $invitee->forceFill([
            'last_message_channel' => 'sms',
            'last_message_status' => 'queued',
            'invitation_sms_status' => 'queued',
        ])->saveQuietly();

        $this->assertTrue($this->isInFlight($invitee));
    }

    public function test_historical_pending_log_does_not_block_after_phone_change(): void
    {
        $invitee = $this->invitee();
        $this->log($invitee, 'queued', 'old-phone-queued');
        $invitee->forceFill([
            'invitation_phone_changed_at' => now(),
            'invitation_sms_reset_log_id' => SmsLog::query()->max('id'),
            'last_message_channel' => 'sms',
            'last_message_status' => 'sent',
            'invitation_sms_status' => 'not_sent',
        ])->saveQuietly();

        $this->assertFalse($this->isInFlight($invitee));
    }
}
