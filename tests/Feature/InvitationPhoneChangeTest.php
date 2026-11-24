<?php

namespace Tests\Feature;

use App\Models\CardType;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\InvitationSmsDeliveryStatusService;
use App\Services\SmsInvitationRecipientsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationPhoneChangeTest extends TestCase
{
    use RefreshDatabase;

    private function invitee(): Invitee
    {
        $event = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Phone Update Test',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);
        $type = CardType::query()->create(['event_id' => $event->id, 'name' => 'Single', 'allowed_guests' => 1]);

        return Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $type->id,
            'name' => 'Guest',
            'phone' => '0712345678',
            'card_status' => Invitee::CARD_STATUS_ACTIVE,
        ]);
    }

    public function test_real_phone_change_preserves_old_receipts_and_makes_new_number_eligible(): void
    {
        $invitee = $this->invitee();
        $oldLog = SmsLog::query()->create([
            'event_id' => $invitee->event_id,
            'invitee_id' => $invitee->id,
            'phone' => '255712345678',
            'sms_type' => SmsLog::TYPE_INVITATION_CARD,
            'message' => 'Original invitation',
            'status' => 'delivered',
            'provider_message_id' => 'old-shoot',
        ]);
        $invitee->forceFill([
            'sms_status' => 'delivered',
            'invitation_sms_status' => 'delivered',
            'sms_sent_at' => now()->subHour(),
        ])->save();

        $invitee->phone = '0766666666';
        $invitee->save();
        $invitee->refresh();

        $this->assertSame('255766666666', $invitee->phone);
        $this->assertSame('not_sent', $invitee->sms_status);
        $this->assertSame('not_sent', $invitee->invitation_sms_status);
        $this->assertNotNull($invitee->invitation_phone_changed_at);
        $this->assertSame($oldLog->id, $invitee->invitation_sms_reset_log_id);
        $this->assertSame('delivered', $oldLog->fresh()->status);
        $this->assertTrue(app(SmsInvitationRecipientsService::class)->eligible($invitee, 'not_sent'));
        $this->assertSame(
            'Not Sent — Phone Updated',
            app(InvitationSmsDeliveryStatusService::class)->forInvitee($invitee)['label'],
        );

        $newLog = SmsLog::query()->create([
            'event_id' => $invitee->event_id,
            'invitee_id' => $invitee->id,
            'phone' => $invitee->phone,
            'sms_type' => SmsLog::TYPE_INVITATION_CARD,
            'message' => 'New invitation',
            'status' => 'sent',
            'provider_message_id' => 'new-shoot',
        ]);

        $this->assertFalse(app(SmsInvitationRecipientsService::class)->eligible($invitee, 'not_sent'));
        $this->assertSame('Submitted', app(InvitationSmsDeliveryStatusService::class)->forInvitee($invitee)['label']);
        $this->assertSame('delivered', $oldLog->fresh()->status);
        $this->assertSame('sent', $newLog->fresh()->status);
    }

    public function test_number_formatting_only_does_not_reset_delivery(): void
    {
        $invitee = $this->invitee();
        $invitee->forceFill(['sms_status' => 'sent', 'invitation_sms_status' => 'sent'])->save();
        $invitee->phone = '+255 712 345 678';
        $invitee->save();
        $invitee->refresh();

        $this->assertSame('sent', $invitee->sms_status);
        $this->assertSame('sent', $invitee->invitation_sms_status);
        $this->assertNull($invitee->invitation_phone_changed_at);
        $this->assertFalse(app(SmsInvitationRecipientsService::class)->eligible($invitee, 'not_sent'));
    }
}
