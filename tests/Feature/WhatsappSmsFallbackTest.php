<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Pages\SendEventMessage;
use App\Jobs\SendInvitationSmsJob;
use App\Models\CardType;
use App\Models\CardTemplate;
use App\Models\Event;
use App\Models\GeneratedCard;
use App\Models\Invitee;
use App\Models\MessageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsappSmsFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_latest_failed_whatsapp_invitation_without_existing_sms_is_eligible(): void
    {
        [$event, $invitee] = $this->fixture();

        $page = new SendEventMessage();
        $page->record = $event;

        $this->log($invitee, 'failed');
        $this->assertSame(1, $page->getFailedWhatsappSmsEligibleCountProperty());

        $this->log($invitee, 'sent');
        $this->assertSame(0, $page->getFailedWhatsappSmsEligibleCountProperty());

        $this->log($invitee, 'failed');
        $invitee->update(['whatsapp_status' => 'queued']);
        $this->assertSame(0, $page->getFailedWhatsappSmsEligibleCountProperty());

        $invitee->update(['whatsapp_status' => 'failed']);
        $this->assertSame(1, $page->getFailedWhatsappSmsEligibleCountProperty());

        $invitee->smsLogs()->create([
            'event_id' => $event->id,
            'sms_type' => 'invitation_card',
            'status' => 'accepted',
            'phone' => $invitee->phone,
        ]);
        $this->assertSame(0, $page->getFailedWhatsappSmsEligibleCountProperty());
    }

    public function test_manual_fallback_queues_one_sms_and_does_not_requeue_on_second_click(): void
    {
        Queue::fake();

        [$event, $invitee] = $this->fixture();
        $this->log($invitee, 'failed');

        $page = new SendEventMessage();
        $page->record = $event;
        $page->sendFailedWhatsappSms();
        $page->sendFailedWhatsappSms();

        Queue::assertPushed(SendInvitationSmsJob::class, 1);
    }

    private function fixture(): array
    {
        $event = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'WhatsApp Fallback Test',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);

        $cardType = CardType::query()->create([
            'event_id' => $event->id,
            'name' => 'Single',
            'allowed_people' => 1,
            'is_active' => true,
        ]);

        $invitee = Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $cardType->id,
            'name' => 'Fallback Guest',
            'phone' => '255754123456',
            'short_code' => 'FALL01',
            'card_status' => Invitee::CARD_STATUS_ACTIVE,
            'whatsapp_status' => 'failed',
        ]);

        $cardTemplate = CardTemplate::query()->create([
            'event_id' => $event->id,
            'name' => 'Invitation Template',
            'template_image' => 'tests/template.png',
            'status' => CardTemplate::STATUS_ACTIVE,
        ]);

        GeneratedCard::query()->create([
            'card_template_id' => $cardTemplate->id,
            'event_id' => $event->id,
            'invitee_id' => $invitee->id,
            'status' => GeneratedCard::STATUS_GENERATED,
            'file_path' => 'tests/test-card.png',
        ]);

        return [$event, $invitee];
    }

    private function log(Invitee $invitee, string $status): void
    {
        MessageLog::query()->create([
            'event_id' => $invitee->event_id,
            'invitee_id' => $invitee->id,
            'channel' => 'whatsapp',
            'type' => 'invitation',
            'phone' => $invitee->phone,
            'status' => $status,
            'error_message' => $status === 'failed' ? 'Undeliverable' : null,
        ]);
    }
}
