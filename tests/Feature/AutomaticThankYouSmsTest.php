<?php

namespace Tests\Feature;

use App\Models\CardType;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\ReminderSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AutomaticThankYouSmsTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_mode_never_sends_automatically(): void
    {
        $event = $this->makeEvent('manual');
        $this->makeInvitee($event);
        $this->makeTemplate($event);

        $sms = $this->mock(ReminderSmsService::class);
        $sms->shouldNotReceive('sendCustomSms');

        $this->artisan('sms:send-automatic-thank-you')->assertExitCode(0);

        $this->assertDatabaseCount('automatic_thank_you_sends', 0);
    }

    public function test_automatic_mode_sends_once_to_checked_in_guests(): void
    {
        $event = $this->makeEvent('automatic');
        $this->makeTemplate($event);
        $included = $this->makeInvitee($event, 'Checked In', 1);
        $this->makeInvitee($event, 'Absent', 0);

        $sms = $this->mock(ReminderSmsService::class);
        $sms->shouldReceive('sendCustomSms')
            ->once()
            ->withArgs(fn ($invitee, $message, $source) => $invitee->id === $included->id
                && str_contains($message, 'Checked In')
                && $source === \App\Models\SmsLog::SOURCE_AUTOMATIC)
            ->andReturn(true);

        $this->artisan('sms:send-automatic-thank-you')->assertExitCode(0);
        $this->artisan('sms:send-automatic-thank-you')->assertExitCode(0);

        $this->assertSame(1, DB::table('automatic_thank_you_sends')->count());
        $this->assertDatabaseHas('automatic_thank_you_sends', [
            'event_id' => $event->id,
            'invitee_id' => $included->id,
            'channel' => 'sms',
            'status' => 'sent',
        ]);
    }

    public function test_automatic_messages_wait_until_the_configured_next_day_time(): void
    {
        $event = $this->makeEvent('automatic');
        $event->update([
            'event_date' => now('Africa/Dar_es_Salaam')->toDateString(),
            'auto_thank_you_time' => '09:00',
        ]);
        $this->makeTemplate($event);
        $this->makeInvitee($event);

        $sms = $this->mock(ReminderSmsService::class);
        $sms->shouldNotReceive('sendCustomSms');

        $this->artisan('sms:send-automatic-thank-you')->assertExitCode(0);

        $this->assertDatabaseCount('automatic_thank_you_sends', 0);
    }

    private function makeEvent(string $mode): Event
    {
        return Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Family Wedding',
            'event_type' => Event::TYPE_WEDDING,
            'event_date' => now()->subDay()->toDateString(),
            'status' => Event::STATUS_COMPLETED,
            'thank_you_mode' => $mode,
            'auto_thank_you_scope' => 'checked_in',
        ]);
    }

    private function makeTemplate(Event $event): MessageTemplate
    {
        return MessageTemplate::query()->create([
            'event_id' => $event->id,
            'channel' => MessageTemplate::CHANNEL_SMS,
            'type' => 'thank_you',
            'name' => 'SMS Thank You',
            'content' => 'Thank you {name} for attending {event_name}.',
            'status' => MessageTemplate::STATUS_ACTIVE,
        ]);
    }

    private function makeInvitee(Event $event, string $name = 'Test Guest', int $checkIns = 1): Invitee
    {
        $cardType = CardType::query()->firstOrCreate(
            ['event_id' => $event->id, 'name' => 'Family'],
            ['allowed_people' => 2, 'is_active' => true],
        );

        return Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $cardType->id,
            'name' => $name,
            'phone' => '255754123456',
            'checked_in_count' => $checkIns,
            'card_status' => Invitee::CARD_STATUS_ACTIVE,
            'rsvp_status' => Invitee::RSVP_ATTENDING,
        ]);
    }
}
