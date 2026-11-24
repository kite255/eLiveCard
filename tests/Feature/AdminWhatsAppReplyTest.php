<?php

namespace Tests\Feature;

use App\Models\CardType;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\MessageLog;
use App\Models\User;
use App\Services\InviteeWhatsAppReplyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminWhatsAppReplyTest extends TestCase
{
    use RefreshDatabase;

    private function invitee(): Invitee
    {
        $event = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Reply Test Event',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);
        $type = CardType::query()->create([
            'event_id' => $event->id,
            'name' => 'Single',
            'allowed_people' => 1,
            'is_active' => true,
        ]);

        return Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $type->id,
            'name' => 'WhatsApp Guest',
            'phone' => '255700000001',
            'last_reply_message' => 'Nitahudhuria',
            'last_reply_at' => now()->subMinutes(10),
            'rsvp_status' => 'attending',
            'whatsapp_status' => 'read',
        ]);
    }

    public function test_reply_is_sent_and_logged_without_changing_invitation_state(): void
    {
        $invitee = $this->invitee();
        $admin = User::factory()->create();
        config([
            'services.whatsapp.access_token' => 'test-whatsapp-token',
            'services.whatsapp.phone_number_id' => '111111',
            'services.whatsapp.base_url' => 'https://graph.facebook.com',
            'services.whatsapp.api_version' => 'v25.0',
        ]);
        Http::fake(['graph.facebook.com/*' => Http::response([
            'messages' => [['id' => 'wamid.TEST.REPLY']],
        ], 200)]);

        $log = app(InviteeWhatsAppReplyService::class)->send(
            $invitee,
            'Asante, tunakutarajia!',
            $admin->id,
        );

        $this->assertSame('sent', $log->status);
        $this->assertSame('custom', $log->type);
        $this->assertSame('wamid.TEST.REPLY', $log->provider_message_id);
        $this->assertSame('admin_reply', $log->meta['purpose']);
        $this->assertSame($admin->id, $log->meta['admin_id']);
        $this->assertSame('read', $invitee->fresh()->whatsapp_status);
        $this->assertSame('Nitahudhuria', $invitee->fresh()->last_reply_message);
        Http::assertSent(fn ($request) =>
            $request->hasHeader('Authorization', 'Bearer test-whatsapp-token')
            && $request['to'] === '255700000001'
            && $request['type'] === 'text'
            && $request['text']['body'] === 'Asante, tunakutarajia!'
        );
    }

    public function test_expired_customer_service_window_prevents_sending(): void
    {
        $invitee = $this->invitee();
        $invitee->forceFill(['last_reply_at' => now()->subHours(25)])->save();
        config(['services.whatsapp.access_token' => 'test', 'services.whatsapp.phone_number_id' => '1234']);
        Http::fake();

        try {
            app(InviteeWhatsAppReplyService::class)->send($invitee, 'Hello', 1);
            $this->fail('Expected closed WhatsApp window validation error.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reply', $e->errors());
        }

        Http::assertNothingSent();
        $this->assertSame(0, MessageLog::query()->count());
    }

    public function test_provider_rejection_records_failure_without_changing_invitation_status(): void
    {
        $invitee = $this->invitee();
        config(['services.whatsapp.access_token' => 'test', 'services.whatsapp.phone_number_id' => '1234']);
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Outside message window']], 400)]);

        try {
            app(InviteeWhatsAppReplyService::class)->send($invitee, 'Hello', 1);
            $this->fail('Expected a rejected WhatsApp reply.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reply', $e->errors());
        }

        $this->assertSame('failed', MessageLog::query()->firstOrFail()->status);
        $this->assertSame('read', $invitee->fresh()->whatsapp_status);
    }
}
