<?php

namespace Tests\Feature;

use App\Jobs\SendInvitationSmsJob;
use App\Jobs\SendInvitationWhatsAppJob;
use App\Models\CardType;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\InviteeInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
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
