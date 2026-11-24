<?php

namespace Tests\Feature;

use App\Models\CardType;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\SmsLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmsDeliveryCallbackIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.sms.delivery_callback_token', 'test-callback-secret');
    }

    private function fixture(): array
    {
        $event = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Delivery Callback Test',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);
        $type = CardType::query()->create([
            'event_id' => $event->id,
            'name' => 'Single',
            'allowed_guests' => 1,
        ]);
        $invitee = Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $type->id,
            'name' => 'Test Recipient',
            'phone' => '255700000010',
            'card_status' => Invitee::CARD_STATUS_ACTIVE,
        ]);
        $log = SmsLog::query()->create([
            'event_id' => $event->id,
            'invitee_id' => $invitee->id,
            'phone' => $invitee->phone,
            'sms_type' => SmsLog::TYPE_INVITATION,
            'message' => 'Test invitation',
            'status' => SmsLog::STATUS_SENT,
            'provider' => 'eLive SMS',
            'provider_message_id' => 'receipt-123',
        ]);

        return [$invitee, $log];
    }

    private function postDeliveryCallback(array $body, bool $authorized = true)
    {
        $request = $this;

        if ($authorized) {
            $request = $request->withHeader('X-SMS-Callback-Token', 'test-callback-secret');
        }

        return $request->postJson('/api/sms/delivery-callback', $body);
    }

    public function test_rejects_unauthenticated_sms_delivery_callback(): void
    {
        [, $log] = $this->fixture();

        $this->postDeliveryCallback(['message_id' => 'receipt-123', 'status' => 'delivered'], false)
            ->assertUnauthorized();

        $this->assertSame(SmsLog::STATUS_SENT, $log->fresh()->status);
    }

    public function test_rejects_callbacks_when_secret_is_not_configured(): void
    {
        [, $log] = $this->fixture();
        config()->set('services.sms.delivery_callback_token', null);

        $this->postJson('/api/sms/delivery-callback', [
            'message_id' => 'receipt-123', 'status' => 'delivered',
        ], ['X-SMS-Callback-Token' => 'test-callback-secret'])->assertUnauthorized();

        $this->assertSame(SmsLog::STATUS_SENT, $log->fresh()->status);
    }

    public function test_unknown_provider_status_does_not_change_log(): void
    {
        [, $log] = $this->fixture();

        $this->postDeliveryCallback(['message_id' => 'receipt-123', 'status' => 'unexpected-provider-value'])
            ->assertUnprocessable();

        $this->assertSame(SmsLog::STATUS_SENT, $log->fresh()->status);
    }

    public function test_submitted_callback_does_not_downgrade_delivery(): void
    {
        [$invitee, $log] = $this->fixture();

        $this->postDeliveryCallback(['message_id' => 'receipt-123', 'status' => 'delivered'])
            ->assertOk();
        $this->assertSame(SmsLog::STATUS_DELIVERED, $log->fresh()->status);

        $this->postDeliveryCallback(['message_id' => 'receipt-123', 'status' => 'submitted'])
            ->assertOk();

        $this->assertSame(SmsLog::STATUS_DELIVERED, $log->fresh()->status);
        $this->assertSame(Invitee::SMS_STATUS_DELIVERED, $invitee->fresh()->sms_status);
    }

    public function test_older_sms_receipt_does_not_replace_newer_invitee_status(): void
    {
        [$invitee, $oldLog] = $this->fixture();

        SmsLog::query()->create([
            'event_id' => $invitee->event_id,
            'invitee_id' => $invitee->id,
            'phone' => $invitee->phone,
            'sms_type' => SmsLog::TYPE_INVITATION,
            'message' => 'More recent invitation',
            'status' => SmsLog::STATUS_SENT,
            'provider' => 'eLive SMS',
            'provider_message_id' => 'receipt-456',
        ]);
        $invitee->forceFill(['sms_status' => Invitee::SMS_STATUS_SENT])->saveQuietly();

        $this->postDeliveryCallback(['message_id' => 'receipt-123', 'status' => 'delivered'])
            ->assertOk();

        $this->assertSame(SmsLog::STATUS_DELIVERED, $oldLog->fresh()->status);
        $this->assertSame(Invitee::SMS_STATUS_SENT, $invitee->fresh()->sms_status);
    }
}
