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
}
