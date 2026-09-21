<?php

namespace Tests\Feature;

use App\Models\ContributionCampaign;
use App\Models\ContributionRecipient;
use App\Models\Event;
use App\Models\User;
use App\Services\WhatsAppApiCloudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContributionCardWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_the_approved_contribution_template_with_image_and_name(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('contributions/lucas.jpg', 'image');

        config()->set([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.driver' => 'meta_cloud_api',
            'services.whatsapp.access_token' => 'test-token',
            'services.whatsapp.phone_number_id' => '123456',
            'services.whatsapp.api_version' => 'v25.0',
            'services.whatsapp.base_url' => 'https://graph.facebook.com',
        ]);

        Http::fake([
            '*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200),
        ]);

        $event = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Committee Event',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);
        $campaign = ContributionCampaign::query()->create([
            'event_id' => $event->id,
            'name' => 'Committee Contributions',
            'template_image' => 'templates/card.jpg',
            'whatsapp_template_name' => 'contribution_card_sw',
            'whatsapp_language_code' => 'sw',
        ]);
        $recipient = ContributionRecipient::query()->create([
            'event_id' => $event->id,
            'contribution_campaign_id' => $campaign->id,
            'name' => 'Kitenken Lucas',
            'phone' => '255754123456',
            'generated_card_path' => 'contributions/lucas.jpg',
            'generation_status' => ContributionRecipient::STATUS_GENERATED,
        ]);

        app(WhatsAppApiCloudService::class)->sendContributionRecipient($recipient);

        Http::assertSent(function (Request $request): bool {
            return data_get($request->data(), 'template.name') === 'contribution_card_sw'
                && data_get($request->data(), 'template.language.code') === 'sw'
                && data_get($request->data(), 'template.components.0.type') === 'header'
                && data_get($request->data(), 'template.components.1.parameters.0.text') === 'Kitenken Lucas';
        });

        $this->assertDatabaseHas('contribution_recipients', [
            'id' => $recipient->id,
            'whatsapp_status' => 'submitted',
            'provider_message_id' => 'wamid.test',
        ]);
        $this->assertDatabaseHas('message_logs', [
            'event_id' => $event->id,
            'contribution_recipient_id' => $recipient->id,
            'type' => 'contribution',
        ]);
    }

    public function test_it_tracks_delivery_and_receiver_comments_from_the_webhook(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('contributions/reply-test.jpg', 'image');

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
            '*' => Http::response(['messages' => [['id' => 'wamid.contribution.test']]], 200),
        ]);

        $event = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Committee Event',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);
        $campaign = ContributionCampaign::query()->create([
            'event_id' => $event->id,
            'name' => 'Committee Contributions',
            'template_image' => 'templates/card.jpg',
            'whatsapp_template_name' => 'contribution_card_sw',
            'whatsapp_language_code' => 'sw',
        ]);
        $recipient = ContributionRecipient::query()->create([
            'event_id' => $event->id,
            'contribution_campaign_id' => $campaign->id,
            'name' => 'Kitenken Lucas',
            'phone' => '255754123456',
            'generated_card_path' => 'contributions/reply-test.jpg',
            'generation_status' => ContributionRecipient::STATUS_GENERATED,
        ]);

        app(WhatsAppApiCloudService::class)->sendContributionRecipient($recipient);

        $this->postJson('/api/whatsapp/webhook', [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => [
                            'phone_number_id' => '123456',
                            'display_phone_number' => '255700000000',
                        ],
                        'statuses' => [[
                            'id' => 'wamid.contribution.test',
                            'recipient_id' => '255754123456',
                            'status' => 'delivered',
                            'timestamp' => (string) now()->timestamp,
                        ]],
                    ],
                ]],
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('contribution_recipients', [
            'id' => $recipient->id,
            'whatsapp_status' => 'delivered',
        ]);

        $this->postJson('/api/whatsapp/webhook', [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => [
                            'phone_number_id' => '123456',
                            'display_phone_number' => '255700000000',
                        ],
                        'messages' => [[
                            'from' => '255754123456',
                            'id' => 'wamid.contribution.reply',
                            'timestamp' => (string) now()->timestamp,
                            'type' => 'text',
                            'text' => [
                                'body' => 'Asante, nitachangia kesho.',
                            ],
                        ]],
                    ],
                ]],
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('contribution_recipients', [
            'id' => $recipient->id,
            'last_reply_message' => 'Asante, nitachangia kesho.',
        ]);
        $this->assertDatabaseHas('message_logs', [
            'contribution_recipient_id' => $recipient->id,
            'type' => 'contribution_reply',
            'status' => 'replied',
        ]);
    }

}
