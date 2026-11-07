<?php

namespace Tests\Feature;

use App\Models\CardType;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\User;
use App\Services\SmsInvitationAudienceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SmsInvitationAudienceTest extends TestCase
{
    use RefreshDatabase;

    private function makeInvitee(Event $event, CardType $cardType, string $name): Invitee
    {
        return Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $cardType->id,
            'name' => $name,
            'phone' => '255700000010',
            'card_status' => Invitee::CARD_STATUS_ACTIVE,
        ]);
    }

    private function trackWhatsApp(Event $event, Invitee $invitee, string $status, $updatedAt): void
    {
        DB::table('whatsapp_template_sends')->insert([
            'event_id' => $event->id,
            'invitee_id' => $invitee->id,
            'message_template_id' => $this->whatsappTemplateId,
            'status' => $status,
            'attempts' => 1,
            'last_attempt_at' => $updatedAt,
            'created_at' => $updatedAt,
            'updated_at' => $updatedAt,
        ]);
    }

    private int $whatsappTemplateId;

    public function test_audiences_allow_verified_whatsapp_fallback_without_resending_accepted_sms(): void
    {
        $event = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'SMS Fallback Event',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);
        $cardType = CardType::query()->create([
            'event_id' => $event->id, 'name' => 'Single', 'allowed_guests' => 1,
        ]);
        $this->whatsappTemplateId = DB::table('message_templates')->insertGetId([
            'event_id' => $event->id,
            'channel' => 'whatsapp',
            'type' => 'invitation',
            'name' => 'WA',
            'content' => 'Hello',
            'whatsapp_template_name' => 'event_invitation',
            'whatsapp_language_code' => 'sw',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $new = $this->makeInvitee($event, $cardType, 'New');
        $waFailed = $this->makeInvitee($event, $cardType, 'WA Failed');
        $waUnconfirmed = $this->makeInvitee($event, $cardType, 'WA Unconfirmed');
        $waDelivered = $this->makeInvitee($event, $cardType, 'WA Delivered');
        $smsFailed = $this->makeInvitee($event, $cardType, 'SMS Failed');
        $smsSentWithoutLog = $this->makeInvitee($event, $cardType, 'Legacy SMS Sent');
        $cancelled = $this->makeInvitee($event, $cardType, 'Cancelled');

        $this->trackWhatsApp($event, $waFailed, 'failed', now()->subDays(2));
        $this->trackWhatsApp($event, $waUnconfirmed, 'submitted', now()->subDays(2));
        $this->trackWhatsApp($event, $waDelivered, 'delivered', now()->subDays(2));
        $this->trackWhatsApp($event, $cancelled, 'failed', now()->subDays(2));

        $smsFailed->forceFill(['invitation_sms_status' => 'failed', 'sms_status' => 'failed'])->save();
        $smsSentWithoutLog->forceFill(['invitation_sms_status' => 'sent', 'sms_status' => 'sent'])->save();
        $cancelled->forceFill(['card_status' => Invitee::CARD_STATUS_CANCELLED])->save();

        $audience = app(SmsInvitationAudienceService::class);

        $ids = fn (string $scope): array => $audience->preview($event, $scope)['eligible']->pluck('id')->all();

        $this->assertContains($new->id, $ids(SmsInvitationAudienceService::NOT_SENT));
        $this->assertNotContains($smsFailed->id, $ids(SmsInvitationAudienceService::NOT_SENT));
        $this->assertSame([$waFailed->id], $ids(SmsInvitationAudienceService::FAILED_WHATSAPP));
        $this->assertSame([$waUnconfirmed->id], $ids(SmsInvitationAudienceService::WHATSAPP_UNDELIVERED));
        $this->assertSame([$smsFailed->id], $ids(SmsInvitationAudienceService::FAILED_SMS));
        $this->assertNotContains($smsSentWithoutLog->id, $ids(SmsInvitationAudienceService::ALL_ACTIVE));
        $this->assertNotContains($cancelled->id, $ids(SmsInvitationAudienceService::ALL_ACTIVE));
        $this->assertNotContains($waDelivered->id, $ids(SmsInvitationAudienceService::FAILED_WHATSAPP));
    }

    public function test_invalid_scope_is_rejected(): void
    {
        $event = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Invalid Scope',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(SmsInvitationAudienceService::class)->preview($event, 'arbitrary');
    }
}
