<?php

namespace Tests\Feature;

use App\Jobs\SendInvitationSmsJob;
use App\Models\CardType;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\InviteeInvitationService;
use App\Services\SmsInvitationRecipientsService;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TanzaniaOnlySmsPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_tanzanian_mobile_numbers_are_supported_for_sms(): void
    {
        $sms = app(SmsService::class);

        foreach (['0712345678', '0623456789', '+255712345678', '255612345678', '712345678'] as $phone) {
            $this->assertTrue($sms->isTanzanianMobileNumber($phone), $phone);
        }

        foreach (['+254714294113', '254701648285', '+260975029316', '12345', '0755ABC123', '+255222000000'] as $phone) {
            $this->assertFalse($sms->isTanzanianMobileNumber($phone), $phone);
        }
    }

    public function test_international_invitee_stays_available_but_is_excluded_from_sms(): void
    {
        Queue::fake();
        $event = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'International guests',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);
        $type = CardType::query()->create(['event_id' => $event->id, 'name' => 'Single', 'allowed_guests' => 1]);
        $international = Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $type->id,
            'name' => 'International Guest',
            'phone' => '+254714294113',
            'card_status' => Invitee::CARD_STATUS_ACTIVE,
        ]);
        MessageTemplate::query()->create([
            'event_id' => $event->id,
            'channel' => 'sms',
            'type' => 'invitation',
            'name' => 'Invitation SMS',
            'content' => 'Habari #NAME# #PRIVATE_INVITATION_URL#',
            'status' => 'active',
        ]);

        $recipients = app(SmsInvitationRecipientsService::class);
        foreach (array_keys($recipients::scopes()) as $scope) {
            $this->assertFalse($recipients->eligible($international, $scope));
        }

        $sender = app(InviteeInvitationService::class);
        $this->assertSame('skipped', $sender->send($event, $international, 'sms')['status']);
        $this->assertSame('skipped', $sender->sendImmediately($event, $international, 'sms')['status']);
        $this->assertSame('skipped', $sender->sendImmediately($event, $international, 'sms', 'Manual resend explicitly confirmed.')['status']);
        Queue::assertNotPushed(SendInvitationSmsJob::class);
        $this->assertSame('+254714294113', $international->fresh()->phone);
    }

    public function test_international_guest_can_check_in_without_queuing_welcome_sms(): void
    {
        Queue::fake();
        $invitee = $this->welcomeInvitee('+254700000001');

        $result = app(\App\Services\CheckInService::class)->checkIn($invitee, 1);

        $this->assertTrue($result['success']);
        $this->assertSame(1, (int) $invitee->fresh()->checked_in_count);
        Queue::assertNotPushed(\App\Jobs\SendWelcomeSmsJob::class);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'invitee.welcome_sms_skipped',
            'subject_id' => $invitee->id,
        ]);
    }

    public function test_queued_international_welcome_sms_exits_without_provider_submission(): void
    {
        $invitee = $this->welcomeInvitee('+260970000001');
        $service = \Mockery::mock(SmsService::class)->makePartial();
        $service->shouldNotReceive('sendCustomMessage');
        (new \App\Jobs\SendWelcomeSmsJob($invitee->id))->handle($service);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'welcome_sms.country_unsupported',
            'subject_id' => $invitee->id,
        ]);
        $this->assertDatabaseMissing('sms_logs', [
            'invitee_id' => $invitee->id,
            'sms_type' => 'welcome_sms',
        ]);
    }

    private function welcomeInvitee(string $phone): Invitee
    {
        $event = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Welcome SMS policy',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);
        $event->forceFill([
            'welcome_sms_enabled' => true,
            'welcome_sms_message' => 'Karibu #NAME#',
        ])->save();
        $type = CardType::query()->create([
            'event_id' => $event->id,
            'name' => 'Single',
            'allowed_guests' => 1,
        ]);

        return Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $type->id,
            'name' => 'International Guest',
            'phone' => $phone,
            'card_status' => Invitee::CARD_STATUS_ACTIVE,
        ]);
    }
}
