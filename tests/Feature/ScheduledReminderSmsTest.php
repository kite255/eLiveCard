<?php

namespace Tests\Feature;

use App\Models\CardType;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\ReminderSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ScheduledReminderSmsTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_day_reminder_is_not_blocked_by_prior_rsvp_reminder(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 10:05:00', 'Africa/Dar_es_Salaam'));
        $event = $this->makeEvent('2026-11-11', [
            'auto_one_day_reminder_enabled' => true,
            'one_day_reminder_time' => '10:00',
        ]);
        $invitee = $this->makeInvitee($event, Invitee::RSVP_STATUS_ATTENDING);
        $this->recordAttempt($invitee, SmsLog::TYPE_RSVP_PENDING_REMINDER);

        $sms = $this->mock(ReminderSmsService::class);
        $sms->shouldReceive('sendBulkAttendingReminders')->once()
            ->withArgs(fn ($recipients, $source) => $source === SmsLog::SOURCE_AUTOMATIC
                && $recipients->modelKeys() === [$invitee->id])
            ->andReturn(['sent' => 1, 'failed' => 0, 'skipped' => 0]);

        $this->artisan('sms:send-one-day-before-reminders')->assertExitCode(0);
    }

    public function test_rsvp_reminder_is_not_repeated_after_an_attempt(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 09:05:00', 'Africa/Dar_es_Salaam'));
        $event = $this->makeEvent('2026-11-12', [
            'auto_rsvp_pending_reminder_enabled' => true,
            'rsvp_pending_reminder_time' => '09:00',
        ]);
        $invitee = $this->makeInvitee($event, Invitee::RSVP_STATUS_PENDING);
        $this->recordAttempt($invitee, SmsLog::TYPE_RSVP_PENDING_REMINDER);

        $sms = $this->mock(ReminderSmsService::class);
        $sms->shouldNotReceive('sendBulkRsvpPendingReminders');

        $this->artisan('sms:send-rsvp-pending-reminders')->assertExitCode(0);
    }

    public function test_event_day_reminder_runs_after_configured_time(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 06:05:00', 'Africa/Dar_es_Salaam'));
        $event = $this->makeEvent('2026-11-10', [
            'auto_event_day_reminder_enabled' => true,
            'event_day_reminder_time' => '06:00',
        ]);
        $invitee = $this->makeInvitee($event, Invitee::RSVP_STATUS_ATTENDING);

        $sms = $this->mock(ReminderSmsService::class);
        $sms->shouldReceive('sendBulkEventDayReminders')->once()
            ->withArgs(fn ($recipients, $source) => $source === SmsLog::SOURCE_AUTOMATIC
                && $recipients->modelKeys() === [$invitee->id])
            ->andReturn(['sent' => 1, 'failed' => 0, 'skipped' => 0]);

        $this->artisan('sms:send-event-day-reminders')->assertExitCode(0);
    }

    public function test_draft_events_do_not_send_scheduled_reminders(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 10:05:00', 'Africa/Dar_es_Salaam'));
        $event = $this->makeEvent('2026-11-11', [
            'status' => Event::STATUS_DRAFT,
            'auto_one_day_reminder_enabled' => true,
            'one_day_reminder_time' => '10:00',
        ]);
        $this->makeInvitee($event, Invitee::RSVP_STATUS_ATTENDING);

        $sms = $this->mock(ReminderSmsService::class);
        $sms->shouldNotReceive('sendBulkAttendingReminders');

        $this->artisan('sms:send-one-day-before-reminders')->assertExitCode(0);
    }

    private function makeEvent(string $date, array $attributes = []): Event
    {
        return Event::query()->create(array_merge([
            'user_id' => User::factory()->create()->id,
            'title' => 'Scheduled Wedding',
            'event_type' => Event::TYPE_WEDDING,
            'event_date' => $date,
            'status' => Event::STATUS_ACTIVE,
            'auto_sms_reminders_enabled' => true,
        ], $attributes));
    }

    private function makeInvitee(Event $event, string $rsvpStatus): Invitee
    {
        $cardType = CardType::query()->create([
            'event_id' => $event->id,
            'name' => 'Single',
            'allowed_people' => 1,
            'is_active' => true,
        ]);

        return Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $cardType->id,
            'name' => 'Schedule Guest',
            'phone' => '255754123456',
            'rsvp_status' => $rsvpStatus,
            'card_status' => Invitee::CARD_STATUS_ACTIVE,
        ]);
    }

    private function recordAttempt(Invitee $invitee, string $smsType): void
    {
        SmsLog::query()->create([
            'event_id' => $invitee->event_id,
            'invitee_id' => $invitee->id,
            'phone' => $invitee->phone,
            'sms_type' => $smsType,
            'message' => 'Test message',
            'status' => SmsLog::STATUS_SENT,
            'send_source' => SmsLog::SOURCE_AUTOMATIC,
        ]);
    }
}
