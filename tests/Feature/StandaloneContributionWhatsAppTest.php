<?php

namespace Tests\Feature;

use App\Models\ContributionCampaign;
use App\Models\ContributionRecipient;
use App\Models\User;
use App\Services\WhatsAppApiCloudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StandaloneContributionWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_plain_template_logs_and_sends_for_a_standalone_campaign(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('contributions/plain.jpg', 'image');
        config()->set([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.driver' => 'meta_cloud_api',
            'services.whatsapp.access_token' => 'test-token',
            'services.whatsapp.phone_number_id' => '123456',
            'services.whatsapp.api_version' => 'v25.0',
            'services.whatsapp.base_url' => 'https://graph.facebook.com',
        ]);

        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.plain']]], 200)]);

        $campaign = ContributionCampaign::query()->create([
            'event_id' => null,
            'created_by' => User::factory()->create()->id,
            'name' => 'Church Contributions',
            'template_image' => 'contributions/plain.jpg',
            'whatsapp_template_name' => 'contribution_plain',
            'whatsapp_language_code' => 'en',
            'include_member_name' => false,
        ]);

        $recipient = ContributionRecipient::query()->create([
            'contribution_campaign_id' => $campaign->id,
            'event_id' => null,
            'name' => 'Sample Member',
            'phone' => '255754123456',
            'generated_card_path' => 'contributions/plain.jpg',
            'generation_status' => ContributionRecipient::STATUS_GENERATED,
        ]);

        app(WhatsAppApiCloudService::class)->sendContributionRecipient($recipient);

        Http::assertSent(fn (Request $request): bool =>
            data_get($request->data(), 'template.name') === 'contribution_plain'
            && data_get($request->data(), 'template.language.code') === 'en'
            && data_get($request->data(), 'template.components.0.type') === 'header'
            && count(data_get($request->data(), 'template.components', [])) === 1
        );

        $this->assertDatabaseHas('message_logs', [
            'event_id' => null,
            'contribution_recipient_id' => $recipient->id,
            'type' => 'contribution',
        ]);

        $this->assertDatabaseHas('contribution_recipients', [
            'id' => $recipient->id,
            'whatsapp_status' => 'submitted',
        ]);
    }
}
