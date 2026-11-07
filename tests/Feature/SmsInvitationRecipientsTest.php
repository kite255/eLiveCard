<?php

namespace Tests\Feature;

use App\Jobs\SendInvitationSmsJob;
use App\Models\CardType;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\InviteeInvitationService;
use App\Services\SmsInvitationRecipientsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SmsInvitationRecipientsTest extends TestCase
{
    use RefreshDatabase;

    private function invitee(): Invitee
    {
        $event = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'SMS Fallback Test',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);
        $cardType = CardType::query()->create([
            'event_id' => $event->id, 'name' => 'Single', 'allowed_guests' => 1,
        ]);

        return Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $cardType->id,
            'name' => 'Test Guest',
            'phone' => '255700000010',
            'card_status' => Invitee::CARD_STATUS_ACTIVE,
        ]);
    }

    private function whatsappTracking(Invitee $invitee, string $status, string $timestamp): void
    {
        $template = MessageTemplate::query()->create([
            'event_id' => $invitee->event_id,
            'channel' => 'whatsapp',
            'type' => 'invitation',
            'name' => 'WhatsApp Invitation',
            'content' => 'Invitation',
            'whatsapp_template_name' => 'event_invitation',
            'whatsapp_language_code' => 'sw',
            'status' => 'active',
        ]);

        DB::table('whatsapp_template_sends')->insert([
            'event_id' => $invitee->event_id,
            'invitee_id' => $invitee->id,
            'message_template_id' => $template->id,
            'status' => $status,
            'attempts' => 1,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    public function test_sms_scopes_include_five_separate_recipient_options(): void
    {
        $this->assertSame([
            'not_sent', 'failed_whatsapp', 'whatsapp_unconfirmed', 'failed_sms', 'all_active',
        ], array_keys(SmsInvitationRecipientsService::scopes()));
    }

    public function test_confirmed_failed_whatsapp_is_eligible_for_sms_fallback(): void
    {
        $invitee = $this->invitee();
        $this->whatsappTracking($invitee, 'failed', now()->subDays(2)->toDateTimeString());
        $recipients = app(SmsInvitationRecipientsService::class);

        $this->assertTrue($recipients->eligible($invitee, 'failed_whatsapp'));
        $this->assertFalse($recipients->eligible($invitee, 'whatsapp_unconfirmed'));

        $invitee->forceFill(['whatsapp_status' => 'read'])->save();
        $this->assertFalse($recipients->eligible($invitee->fresh(), 'failed_whatsapp'));
    }

    public function test_whatsapp_unconfirmed_requires_waiting_period_and_excludes_failed(): void
    {
        $invitee = $this->invitee();
        $this->whatsappTracking($invitee, 'submitted', now()->subDays(2)->toDateTimeString());
        $recipients = app(SmsInvitationRecipientsService::class);

        $this->assertTrue($recipients->eligible($invitee, 'whatsapp_unconfirmed'));
        DB::table('whatsapp_template_sends')->where('invitee_id', $invitee->id)
            ->update(['updated_at' => now()]);
        $this->assertFalse($recipients->eligible($invitee, 'whatsapp_unconfirmed'));
    }

    public function test_sent_sms_without_logs_is_not_requeued_as_fallback(): void
    {
        Queue::fake();
        $invitee = $this->invitee();
        $invitee->forceFill([
            'sms_status' => 'sent',
            'invitation_sms_status' => 'sent',
        ])->save();
        $recipients = app(SmsInvitationRecipientsService::class);

        foreach (array_keys($recipients::scopes()) as $scope) {
            $this->assertFalse($recipients->eligible($invitee, $scope));
        }

        MessageTemplate::query()->create([
            'event_id' => $invitee->event_id,
            'channel' => 'sms',
            'type' => 'invitation',
            'name' => 'SMS Invitation',
            'content' => 'Habari #NAME# #PRIVATE_INVITATION_URL#',
            'status' => 'active',
        ]);

        $result = app(InviteeInvitationService::class)->send($invitee->event, $invitee, 'sms');
        $this->assertSame('skipped', $result['status']);
        Queue::assertNotPushed(SendInvitationSmsJob::class);
    }

    public function test_cancelled_invitee_and_queued_sms_are_excluded(): void
    {
        $invitee = $this->invitee();
        $recipients = app(SmsInvitationRecipientsService::class);

        $this->assertTrue($recipients->eligible($invitee, 'not_sent'));
        $invitee->forceFill(['last_message_channel' => 'sms', 'last_message_status' => 'queued'])->save();
        $this->assertFalse($recipients->eligible($invitee->fresh(), 'not_sent'));

        $invitee->forceFill(['card_status' => Invitee::CARD_STATUS_CANCELLED])->save();
        $this->assertFalse($recipients->eligible($invitee->fresh(), 'all_active'));
    }
}
