<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MessageTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmsTemplateLanguageCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_sms_template_can_be_saved_without_whatsapp_language_code(): void
    {
        $event = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'SMS Template Test Event',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);

        $template = MessageTemplate::query()->create([
            'event_id' => $event->id,
            'channel' => MessageTemplate::CHANNEL_SMS,
            'type' => MessageTemplate::TYPE_INVITATION,
            'name' => 'SMS Invitation',
            'content' => 'Habari #NAME#, umealikwa kwenye #EVENT_NAME#.',
            'whatsapp_template_name' => null,
            'whatsapp_language_code' => null,
            'whatsapp_buttons' => null,
            'status' => MessageTemplate::STATUS_ACTIVE,
        ]);

        $this->assertNull($template->fresh()->whatsapp_language_code);
        $this->assertSame('sms', $template->fresh()->channel);
    }

    public function test_whatsapp_template_keeps_explicit_language_code(): void
    {
        $event = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'WhatsApp Template Test Event',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);

        $template = MessageTemplate::query()->create([
            'event_id' => $event->id,
            'channel' => MessageTemplate::CHANNEL_WHATSAPP,
            'type' => MessageTemplate::TYPE_INVITATION,
            'name' => 'WhatsApp Invitation',
            'content' => 'Habari #NAME#',
            'whatsapp_template_name' => 'event_invitation',
            'whatsapp_language_code' => 'sw',
            'status' => MessageTemplate::STATUS_ACTIVE,
        ]);

        $this->assertSame('sw', $template->fresh()->whatsapp_language_code);
    }
}
