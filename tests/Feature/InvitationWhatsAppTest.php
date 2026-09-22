<?php

namespace Tests\Feature;

use App\Models\CardTemplate;
use App\Models\CardType;
use App\Models\Event;
use App\Models\GeneratedCard;
use App\Models\Invitee;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\WhatsAppApiCloudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvitationWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_an_invitation_card_template_and_tracks_delivery_and_reply(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('cards/lucas.jpg', 'image');

        config()->set([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.driver' => 'meta_cloud_api',
            'services.whatsapp.access_token' => 'test-token',
            'services.whatsapp.phone_number_id' => '123456',
            'services.whatsapp.api_version' => 'v25.0',
            'services.whatsapp.base_url' => 'https://graph.facebook.com',
            'services.whatsapp.verify_webhook_signature' => false,
        ]);

        Http::fake([
            '*' => Http::response(['messages' => [['id' => 'wamid.invitation.test']]], 200),
        ]);

        $event = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Wedding Event',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);
        $cardType = CardType::query()->create([
            'event_id' => $event->id,
            'name' => 'Family',
            'allowed_people' => 4,
            'is_active' => true,
        ]);
        $cardTemplate = CardTemplate::query()->create([
            'event_id' => $event->id,
            'name' => 'Main Invitation',
            'template_image' => 'templates/invitation.jpg',
            'status' => CardTemplate::STATUS_ACTIVE,
        ]);
        MessageTemplate::query()->create([
            'event_id' => $event->id,
            'channel' => MessageTemplate::CHANNEL_WHATSAPP,
            'type' => MessageTemplate::TYPE_INVITATION,
            'name' => 'English Invitation',
            'content' => 'Invitation',
            'whatsapp_template_name' => 'event_invitation_en',
            'whatsapp_language_code' => MessageTemplate::LANGUAGE_ENGLISH,
            'status' => MessageTemplate::STATUS_ACTIVE,
        ]);
        $invitee = Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $cardType->id,
            'name' => 'Kitenken Lucas',
            'phone' => '255754123456',
            'short_code' => 'ABC123',
        ]);
        GeneratedCard::query()->create([
            'event_id' => $event->id,
            'invitee_id' => $invitee->id,
            'card_template_id' => $cardTemplate->id,
            'file_path' => 'cards/lucas.jpg',
            'status' => GeneratedCard::STATUS_GENERATED,
            'generated_at' => now(),
        ]);

        app(WhatsAppApiCloudService::class)->sendInvitation($invitee);

        Http::assertSent(fn (Request $request): bool =>
            data_get($request->data(), 'template.name') === 'event_invitation_en'
            && data_get($request->data(), 'template.components.0.type') === 'header'
            && data_get($request->data(), 'template.components.1.parameters.0.text') === 'Kitenken Lucas'
            && data_get($request->data(), 'template.components.2.parameters.0.text') === 'ABC123'
        );

        $this->postJson('/api/whatsapp/webhook', [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => ['phone_number_id' => '123456'],
                        'statuses' => [[
                            'id' => 'wamid.invitation.test',
                            'recipient_id' => '255754123456',
                            'status' => 'delivered',
                            'timestamp' => (string) now()->timestamp,
                        ]],
                    ],
                ]],
            ]],
        ])->assertOk();

        $this->postJson('/api/whatsapp/webhook', [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => ['phone_number_id' => '123456'],
                        'messages' => [[
                            'from' => '255754123456',
                            'id' => 'wamid.invitation.reply',
                            'timestamp' => (string) now()->timestamp,
                            'type' => 'text',
                            'text' => ['body' => 'Thank you, I will attend.'],
                        ]],
                    ],
                ]],
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('invitees', [
            'id' => $invitee->id,
            'whatsapp_status' => 'delivered',
            'last_reply_message' => 'Thank you, I will attend.',
        ]);
    }
}
