<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Pages\InviteeResponseTracker;
use App\Jobs\SendInvitationSmsJob;
use App\Jobs\SendInvitationWhatsAppJob;
use App\Models\CardType;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\MessageTemplate;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\InviteeInvitationService;
use App\Services\SmsService;
use App\Services\WhatsAppApiCloudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class InviteeDedicatedInvitationActionsTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Event
    {
        return Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Test Wedding',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);
    }

    private function invitee(Event $event): Invitee
    {
        $cardType = CardType::query()->create([
            'event_id' => $event->id,
            'name' => 'Single',
            'allowed_guests' => 1,
        ]);

        return Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $cardType->id,
            'name' => 'Asha Mgeni',
            'phone' => '255700000010',
            'card_status' => Invitee::CARD_STATUS_ACTIVE,
        ]);
    }

    private function template(Event $event, string $channel, string $type = 'invitation'): MessageTemplate
    {
        return MessageTemplate::query()->create([
            'event_id' => $event->id,
            'channel' => $channel,
            'type' => $type,
            'name' => 'Event Invitation',
            'content' => 'Habari #NAME#, fungua kadi: #PRIVATE_INVITATION_URL#',
            'whatsapp_template_name' => $channel === 'whatsapp' ? 'event_invitation' : null,
            'whatsapp_language_code' => $channel === 'whatsapp' ? 'sw' : null,
            'status' => MessageTemplate::STATUS_ACTIVE,
        ]);
    }

    public function test_sms_action_uses_active_invitation_template_and_does_not_queue_a_duplicate(): void
    {
        Queue::fake();
        $event = $this->event();
        $invitee = $this->invitee($event);
        $this->template($event, 'sms');
        $service = app(InviteeInvitationService::class);

        $this->assertStringContainsString('Asha Mgeni', $service->preview($invitee, 'sms'));
        $this->assertSame('queued', $service->send($event, $invitee, 'sms')['status']);
        $this->assertSame('skipped', $service->send($event, $invitee->fresh(), 'sms')['status']);

        Queue::assertPushed(SendInvitationSmsJob::class, 1);
        Queue::assertNotPushed(SendInvitationWhatsAppJob::class);
    }

    public function test_sms_rejects_an_international_number_that_resembles_a_tanzanian_local_number(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid Tanzania phone number');

        app(SmsService::class)->formatPhone('+676771234');
    }

    public function test_single_sms_submit_is_immediate_and_does_not_enqueue(): void
    {
        Queue::fake();
        $event = $this->event();
        $invitee = $this->invitee($event);
        $this->template($event, 'sms');

        $gateway = \Mockery::mock(SmsService::class);
        $gateway->shouldReceive('sendInvitation')->once()
            ->andReturnUsing(function (Invitee $recipient, string $message) use ($invitee): array {
                $this->assertSame($invitee->id, $recipient->id);
                $this->assertStringContainsString('Asha Mgeni', $message);
                $recipient->forceFill([
                    'sms_status' => Invitee::SMS_STATUS_SENT,
                    'invitation_sms_status' => Invitee::SMS_STATUS_SENT,
                    'last_message_status' => 'sent',
                ])->save();
                return ['success' => true, 'driver' => 'http', 'status' => 'sent'];
            });
        app()->instance(SmsService::class, $gateway);

        $service = app(InviteeInvitationService::class);
        $this->assertSame('submitted', $service->sendImmediately($event, $invitee, 'sms')['status']);
        Queue::assertNothingPushed();
        $this->assertSame('skipped', $service->sendImmediately($event, $invitee->fresh(), 'sms')['status']);
        Queue::assertNothingPushed();
    }

    public function test_single_sms_logged_driver_is_not_reported_as_submitted(): void
    {
        Queue::fake();
        $event = $this->event();
        $invitee = $this->invitee($event);
        $this->template($event, 'sms');

        $gateway = \Mockery::mock(SmsService::class);
        $gateway->shouldReceive('sendInvitation')->once()
            ->andReturn(['success' => true, 'driver' => 'log', 'status' => 'logged']);
        app()->instance(SmsService::class, $gateway);

        $this->assertSame('logged', app(InviteeInvitationService::class)
            ->sendImmediately($event, $invitee, 'sms')['status']);
        Queue::assertNothingPushed();
    }

    public function test_single_whatsapp_submit_is_immediate_and_claim_is_not_reused(): void
    {
        Queue::fake();
        $event = $this->event();
        $invitee = $this->invitee($event);
        $template = $this->template($event, 'whatsapp');
        $event->forceFill(['default_whatsapp_template_id' => $template->id])->save();

        $gateway = \Mockery::mock(WhatsAppApiCloudService::class);
        $gateway->shouldReceive('sendInvitation')->once()
            ->andReturn(['messages' => [['id' => 'wamid.test']]]);

        app()->instance(WhatsAppApiCloudService::class, $gateway);

        $service = app(InviteeInvitationService::class);
        $this->assertSame('submitted', $service->sendImmediately($event, $invitee, 'whatsapp')['status']);
        $this->assertDatabaseHas('whatsapp_template_sends', [
            'event_id' => $event->id,
            'invitee_id' => $invitee->id,
            'message_template_id' => $template->id,
            'status' => 'submitted',
        ]);
        $this->assertSame('skipped', $service->sendImmediately($event, $invitee->fresh(), 'whatsapp')['status']);
        Queue::assertNothingPushed();
    }

    public function test_explicit_whatsapp_resend_reuses_the_invitation_template_without_queueing(): void
    {
        Queue::fake();
        $event = $this->event();
        $invitee = $this->invitee($event);
        $template = $this->template($event, 'whatsapp');
        $event->forceFill(['default_whatsapp_template_id' => $template->id])->save();

        $gateway = \Mockery::mock(WhatsAppApiCloudService::class);
        $gateway->shouldReceive('sendInvitation')->twice()
            ->withArgs(fn (Invitee $recipient, string $language, int $templateId) =>
                $recipient->id === $invitee->id && $language === 'sw' && $templateId === $template->id)
            ->andReturn(['messages' => [['id' => 'wamid.invitation']]]);
        app()->instance(WhatsAppApiCloudService::class, $gateway);

        $service = app(InviteeInvitationService::class);
        $this->assertSame('submitted', $service->sendImmediately($event, $invitee, 'whatsapp')['status']);
        $this->assertSame('submitted', $service->sendImmediately($event, $invitee, 'whatsapp', 'Guest requested another copy.')['status']);
        $this->assertDatabaseHas('whatsapp_template_sends', [
            'invitee_id' => $invitee->id,
            'message_template_id' => $template->id,
            'status' => 'submitted',
            'attempts' => 2,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'invitation_whatsapp.force_resend_requested']);
        Queue::assertNothingPushed();
    }

    public function test_whatsapp_resend_skips_a_queued_invitation(): void
    {
        Queue::fake();
        $event = $this->event();
        $invitee = $this->invitee($event);
        $template = $this->template($event, 'whatsapp');
        $event->forceFill(['default_whatsapp_template_id' => $template->id])->save();
        $gateway = \Mockery::mock(WhatsAppApiCloudService::class);
        $gateway->shouldNotReceive('sendInvitation');
        app()->instance(WhatsAppApiCloudService::class, $gateway);

        $service = app(InviteeInvitationService::class);
        $this->assertSame('queued', $service->send($event, $invitee, 'whatsapp')['status']);
        $this->assertSame('skipped', $service->sendImmediately($event, $invitee, 'whatsapp', 'Guest requested another copy.')['status']);
        Queue::assertPushed(SendInvitationWhatsAppJob::class, 1);
    }

    public function test_explicit_sms_resend_submits_a_delivered_invitation_without_queueing(): void
    {
        Queue::fake();
        $event = $this->event();
        $invitee = $this->invitee($event);
        $invitee->forceFill(['invitation_sms_status' => 'delivered', 'sms_status' => 'delivered'])->save();
        $this->template($event, 'sms');

        $gateway = \Mockery::mock(SmsService::class)->makePartial();
        $gateway->shouldReceive('sendInvitation')->once()
            ->withArgs(fn (Invitee $recipient, string $message) =>
                $recipient->id === $invitee->id && str_contains($message, 'Asha Mgeni'))
            ->andReturn(['success' => true, 'driver' => 'http']);
        app()->instance(SmsService::class, $gateway);

        $service = app(InviteeInvitationService::class);
        $this->assertSame('skipped', $service->sendImmediately($event, $invitee, 'sms')['status']);
        $this->assertSame('submitted', $service->sendImmediately($event, $invitee, 'sms', 'Guest requested another copy.')['status']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'invitation_sms.force_resend_requested']);
        Queue::assertNothingPushed();
    }

    public function test_response_tracker_shows_the_latest_invitation_sms_receipt_instead_of_the_stored_invitee_status(): void
    {
        $event = $this->event();
        $invitee = $this->invitee($event);
        $invitee->forceFill(['sms_status' => 'sent', 'sms_sent_at' => now()])->save();
        $log = $invitee->smsLogs()->create([
            'event_id' => $event->id,
            'phone' => $invitee->phone,
            'sms_type' => SmsLog::TYPE_INVITATION_CARD,
            'message' => 'Invitation',
            'status' => 'sent',
            'provider_status' => 'delivery_report_unavailable',
        ]);
        $invitee->smsLogs()->create([
            'event_id' => $event->id,
            'phone' => $invitee->phone,
            'sms_type' => SmsLog::TYPE_EVENT_DAY_REMINDER,
            'message' => 'Reminder',
            'status' => 'failed',
        ]);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'is_active' => true]));
        Livewire::test(InviteeResponseTracker::class, ['record' => $event])
            ->assertSee('Sent · Delivery report unavailable');

        $log->update([
            'status' => 'delivered',
            'provider_status' => 'Delivered',
            'delivered_at' => '2026-11-22 18:41:00',
        ]);
        Livewire::test(InviteeResponseTracker::class, ['record' => $event])
            ->assertSee('22 Nov 18:41')
            ->assertDontSee('Sent · Delivery report unavailable');
    }

    public function test_response_tracker_resends_whatsapp_through_the_gateway(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'is_active' => true]);
        $event = $this->event();
        $invitee = $this->invitee($event);
        $template = $this->template($event, 'whatsapp');
        $event->forceFill(['default_whatsapp_template_id' => $template->id])->save();

        $gateway = \Mockery::mock(WhatsAppApiCloudService::class);
        $gateway->shouldReceive('sendInvitation')->once()
            ->andReturn(['messages' => [['id' => 'wamid.tracker']]]);
        app()->instance(WhatsAppApiCloudService::class, $gateway);

        $this->actingAs($user);

        Livewire::test(InviteeResponseTracker::class, ['record' => $event])
            ->call('resendWhatsAppInvitation', $invitee->id)
            ->assertStatus(200);

        $this->assertDatabaseHas('whatsapp_template_sends', [
            'invitee_id' => $invitee->id,
            'status' => 'submitted',
        ]);
    }

    public function test_immediate_whatsapp_failure_marks_claim_failed_without_queueing(): void
    {
        Queue::fake();
        $event = $this->event();
        $invitee = $this->invitee($event);
        $template = $this->template($event, 'whatsapp');
        $event->forceFill(['default_whatsapp_template_id' => $template->id])->save();

        $gateway = \Mockery::mock(WhatsAppApiCloudService::class);
        $gateway->shouldReceive('sendInvitation')->once()
            ->andThrow(new \RuntimeException('Meta rejected this template'));
        app()->instance(WhatsAppApiCloudService::class, $gateway);

        $this->assertSame('failed', app(InviteeInvitationService::class)
            ->sendImmediately($event, $invitee, 'whatsapp')['status']);
        $this->assertDatabaseHas('whatsapp_template_sends', [
            'event_id' => $event->id,
            'invitee_id' => $invitee->id,
            'message_template_id' => $template->id,
            'status' => 'failed',
        ]);
        Queue::assertNothingPushed();
    }

    public function test_force_resend_requires_reason_and_queues_only_explicit_override(): void
    {
        Queue::fake();
        $event = $this->event();
        $invitee = $this->invitee($event);
        $this->template($event, 'sms');
        $invitee->forceFill([
            'invitation_sms_status' => 'sent',
            'sms_status' => 'sent',
            'last_message_channel' => 'sms',
            'last_message_status' => 'sent',
        ])->save();

        $service = app(InviteeInvitationService::class);

        $this->assertSame('failed', $service->forceResendSms($event, $invitee, 'short')['status']);
        Queue::assertNothingPushed();

        $this->assertSame('skipped', $service->send($event, $invitee, 'sms')['status']);
        $this->assertSame('queued', $service->forceResendSms($event, $invitee, 'Customer requested another SMS copy.')['status']);

        Queue::assertPushed(SendInvitationSmsJob::class, function (SendInvitationSmsJob $job): bool {
            return $job->forceResend === true;
        });
        Queue::assertPushed(SendInvitationSmsJob::class, 1);

        $this->assertSame('skipped', $service->forceResendSms($event, $invitee->fresh(), 'Trying twice for same guest.')['status']);
        Queue::assertPushed(SendInvitationSmsJob::class, 1);
    }

    public function test_force_resend_rejects_cancelled_or_wrong_event_invitees(): void
    {
        Queue::fake();
        $event = $this->event();
        $invitee = $this->invitee($event);
        $other = $this->event();
        $this->template($event, 'sms');
        $service = app(InviteeInvitationService::class);

        $this->assertSame('failed', $service->forceResendSms($other, $invitee, 'Need another invitation SMS.')['status']);

        $invitee->forceFill(['card_status' => Invitee::CARD_STATUS_CANCELLED])->save();
        $this->assertSame('failed', $service->forceResendSms($event, $invitee->fresh(), 'Need another invitation SMS.')['status']);
        Queue::assertNothingPushed();
    }

    public function test_whatsapp_action_uses_event_default_template_once(): void
    {
        Queue::fake();
        $event = $this->event();
        $invitee = $this->invitee($event);
        $template = $this->template($event, 'whatsapp');
        $event->forceFill(['default_whatsapp_template_id' => $template->id])->save();
        $service = app(InviteeInvitationService::class);

        $this->assertStringContainsString('event_invitation', $service->preview($invitee, 'whatsapp'));
        $this->assertSame('queued', $service->send($event, $invitee, 'whatsapp')['status']);
        $this->assertSame('skipped', $service->send($event, $invitee->fresh(), 'whatsapp')['status']);
        $this->assertDatabaseHas('whatsapp_template_sends', [
            'event_id' => $event->id,
            'invitee_id' => $invitee->id,
            'message_template_id' => $template->id,
            'status' => 'pending',
        ]);

        Queue::assertPushed(SendInvitationWhatsAppJob::class, 1);
    }

    public function test_invalid_event_and_noninvitation_whatsapp_template_are_not_dispatched(): void
    {
        Queue::fake();
        $event = $this->event();
        $invitee = $this->invitee($event);
        $other = $this->event();
        $template = $this->template($event, 'whatsapp', 'thank_you');
        $event->forceFill(['default_whatsapp_template_id' => $template->id])->save();

        $service = app(InviteeInvitationService::class);

        $this->assertSame('failed', $service->send($other, $invitee, 'sms')['status']);
        $this->assertSame('failed', $service->send($event, $invitee, 'whatsapp')['status']);
        Queue::assertNothingPushed();
    }
}
