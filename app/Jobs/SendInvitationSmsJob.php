<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\Invitee;
use App\Models\SmsLog;
use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendInvitationSmsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $backoff = 10;

    public function __construct(
        public int $eventId,
        public int $inviteeId,
    ) {}

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('send-invitation-sms-' . $this->eventId . '-' . $this->inviteeId))
                ->releaseAfter(10)
                ->expireAfter(180),
        ];
    }

    public function handle(SmsService $smsService): void
    {
        $event = Event::findOrFail($this->eventId);
        $invitee = Invitee::findOrFail($this->inviteeId);

        if (blank($invitee->phone)) {
            return;
        }

        if (blank($invitee->short_code)) {
            return;
        }

        $privateLink = $invitee->private_invitation_url
            ?? url('/i/' . $invitee->short_code);

        $eventName = $event->title ?? $event->name ?? 'our event';
        $eventDate = $event->event_date
            ? \Carbon\Carbon::parse($event->event_date)->format('d M Y')
            : 'Date to be announced';

        $venue = $event->venue_name
            ?? $event->venue
            ?? 'Venue to be announced';

        $message = "Hello {$invitee->name}, you are invited to {$eventName}.\n"
            . "Date: {$eventDate}\n"
            . "Venue: {$venue}\n"
            . "View your invitation card here: {$privateLink}\n"
            . "Thank you.";

        $smsLog = SmsLog::create([
            'event_id' => $event->id,
            'invitee_id' => $invitee->id,
            'phone' => $invitee->phone,
            'sms_type' => 'invitation',
            'message' => $message,
            'status' => 'pending',
            'send_source' => 'send_message_page',
        ]);

        try {
            $response = null;

            if (method_exists($smsService, 'send')) {
                $response = $smsService->send($invitee->phone, $message);
            } elseif (method_exists($smsService, 'sendSms')) {
                $response = $smsService->sendSms($invitee->phone, $message);
            } elseif (method_exists($smsService, 'sendMessage')) {
                $response = $smsService->sendMessage($invitee->phone, $message);
            } else {
                throw new \RuntimeException('No supported send method found in SmsService.');
            }

            $smsLog->update([
                'status' => 'sent',
                'provider_response' => is_string($response)
                    ? $response
                    : json_encode($response),
                'sent_at' => now(),
            ]);

            $invitee->forceFill([
                'card_status' => 'sent',
            ])->save();
        } catch (Throwable $e) {
            $smsLog->update([
                'status' => 'failed',
                'provider_response' => $e->getMessage(),
            ]);

            Log::error('Invitation SMS failed', [
                'event_id' => $event->id,
                'invitee_id' => $invitee->id,
                'phone' => $invitee->phone,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}