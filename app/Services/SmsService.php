<?php

namespace App\Services;

use App\Models\Invitee;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SmsService
{
    public function sendInvitation(Invitee $invitee): array
    {
        $invitee->loadMissing(['event', 'cardType']);

        try {
            $phone = $this->formatPhone($invitee->phone);
            $message = $this->buildInvitationMessage($invitee);
            $reference = 'elive_invitation_' . $invitee->id . '_' . Str::random(8);

            $response = $this->send(
                phone: $phone,
                message: $message,
                reference: $reference
            );

            $messageId = $response['shoot_id']
                ?? $response['message_id']
                ?? $reference;

            if (method_exists($invitee, 'markInvitationSmsAsSent')) {
                $invitee->markInvitationSmsAsSent($messageId);
            } else {
                $invitee->markSmsAsSent($messageId);
            }

            return $response;
        } catch (\Throwable $e) {
            if (method_exists($invitee, 'markInvitationSmsAsFailed')) {
                $invitee->markInvitationSmsAsFailed($e->getMessage());
            } else {
                $invitee->markSmsAsFailed($e->getMessage());
            }

            throw $e;
        }
    }

    public function sendRsvpPendingReminder(Invitee $invitee): array
    {
        $invitee->loadMissing(['event', 'cardType']);

        try {
            $phone = $this->formatPhone($invitee->phone);
            $message = $this->buildRsvpPendingReminderMessage($invitee);
            $reference = 'elive_rsvp_reminder_' . $invitee->id . '_' . Str::random(8);

            $response = $this->send(
                phone: $phone,
                message: $message,
                reference: $reference
            );

            $messageId = $response['shoot_id']
                ?? $response['message_id']
                ?? $reference;

            if (method_exists($invitee, 'markReminderSmsAsSent')) {
                $invitee->markReminderSmsAsSent($messageId);
            } else {
                $invitee->markSmsAsSent($messageId);
            }

            return $response;
        } catch (\Throwable $e) {
            if (method_exists($invitee, 'markReminderSmsAsFailed')) {
                $invitee->markReminderSmsAsFailed($e->getMessage());
            } else {
                $invitee->markSmsAsFailed($e->getMessage());
            }

            throw $e;
        }
    }

    public function sendAttendingReminder(Invitee $invitee): array
    {
        $invitee->loadMissing(['event', 'cardType']);

        try {
            $phone = $this->formatPhone($invitee->phone);
            $message = $this->buildAttendingReminderMessage($invitee);
            $reference = 'elive_attending_reminder_' . $invitee->id . '_' . Str::random(8);

            $response = $this->send(
                phone: $phone,
                message: $message,
                reference: $reference
            );

            $messageId = $response['shoot_id']
                ?? $response['message_id']
                ?? $reference;

            if (method_exists($invitee, 'markReminderSmsAsSent')) {
                $invitee->markReminderSmsAsSent($messageId);
            } else {
                $invitee->markSmsAsSent($messageId);
            }

            return $response;
        } catch (\Throwable $e) {
            if (method_exists($invitee, 'markReminderSmsAsFailed')) {
                $invitee->markReminderSmsAsFailed($e->getMessage());
            } else {
                $invitee->markSmsAsFailed($e->getMessage());
            }

            throw $e;
        }
    }

    public function sendEventDayReminder(Invitee $invitee): array
    {
        $invitee->loadMissing(['event', 'cardType']);

        try {
            $phone = $this->formatPhone($invitee->phone);
            $message = $this->buildEventDayReminderMessage($invitee);
            $reference = 'elive_event_day_' . $invitee->id . '_' . Str::random(8);

            $response = $this->send(
                phone: $phone,
                message: $message,
                reference: $reference
            );

            $messageId = $response['shoot_id']
                ?? $response['message_id']
                ?? $reference;

            if (method_exists($invitee, 'markFinalSmsAsSent')) {
                $invitee->markFinalSmsAsSent($messageId);
            } else {
                $invitee->markSmsAsSent($messageId);
            }

            return $response;
        } catch (\Throwable $e) {
            if (method_exists($invitee, 'markFinalSmsAsFailed')) {
                $invitee->markFinalSmsAsFailed($e->getMessage());
            } else {
                $invitee->markSmsAsFailed($e->getMessage());
            }

            throw $e;
        }
    }

    public function send(string $phone, string $message, ?string $reference = null): array
    {
        $driver = config('services.sms.driver', 'log');

        if ($driver === 'log') {
            Log::info('eLive Card SMS', [
                'to' => $phone,
                'message' => $message,
                'reference' => $reference,
            ]);

            return [
                'success' => true,
                'message_id' => $reference,
                'driver' => 'log',
            ];
        }

        if ($driver !== 'http') {
            throw new \Exception('Unsupported SMS driver: ' . $driver);
        }

        $apiUrl = config('services.sms.api_url');
        $apiKey = config('services.sms.api_key');
        $apiSecret = config('services.sms.api_secret');
        $senderId = config('services.sms.sender_id', 'eLiveCard');

        if (blank($apiUrl)) {
            throw new \Exception('SMS_API_URL is not configured.');
        }

        if (blank($apiKey)) {
            throw new \Exception('SMS_API_KEY is not configured.');
        }

        if (blank($apiSecret)) {
            throw new \Exception('SMS_API_SECRET is not configured.');
        }

        $payload = [
            'senderId' => $senderId,
            'messageType' => 'text',
            'message' => $message,
            'contacts' => $phone,
        ];

        Log::info('Sending eLive Card SMS request', [
            'url' => $apiUrl,
            'payload' => [
                'senderId' => $payload['senderId'],
                'messageType' => $payload['messageType'],
                'message' => $payload['message'],
                'contacts' => $payload['contacts'],
            ],
            'reference' => $reference,
        ]);

        $response = Http::timeout((int) config('services.sms.timeout', 30))
            ->acceptJson()
            ->withHeaders([
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
                'Content-Type' => 'application/json',
            ])
            ->post($apiUrl, $payload);

        $body = $response->body();
        $data = $response->json() ?? [];

        Log::info('eLive Card SMS provider response', [
            'status' => $response->status(),
            'body' => $body,
            'json' => $data,
            'reference' => $reference,
        ]);

        if ($response->failed()) {
            throw new \Exception('SMS sending failed: ' . $body);
        }

        if (($data['success'] ?? false) !== true) {
            throw new \Exception('SMS provider rejected the message: ' . json_encode($data));
        }

        return [
            'success' => true,
            'shoot_id' => $data['data']['shootId'] ?? null,
            'message_id' => $data['data']['shootId'] ?? $reference,
            'valid_contacts' => $data['data']['validContacts'] ?? null,
            'invalid_contacts' => $data['data']['invalidContacts'] ?? null,
            'duplicated_contacts' => $data['data']['duplicatedContacts'] ?? null,
            'message_size' => $data['data']['messageSize'] ?? null,
            'provider_message' => $data['message'] ?? null,
            'response' => $data,
        ];
    }

    public function buildInvitationMessage(Invitee $invitee): string
    {
        $invitee->loadMissing(['event', 'cardType']);

        $eventName = $this->eventName($invitee);
        $venue = $this->eventVenue($invitee);
        $date = $this->eventDate($invitee);
        $time = $this->eventTime($invitee);
        $rsvpLink = $this->rsvpLink($invitee);

        return "Dear {$invitee->name}, you are invited to {$eventName}.\n"
            . "Date: {$date}\n"
            . "Time: {$time}\n"
            . "Venue: {$venue}\n\n"
            . "Please confirm attendance here:\n{$rsvpLink}\n\n"
            . "Serial: {$invitee->serial_number}\n"
            . "Guests: {$invitee->final_allowed_guests}\n\n"
            . "eLive Card";
    }

    public function buildRsvpPendingReminderMessage(Invitee $invitee): string
    {
        $invitee->loadMissing(['event', 'cardType']);

        $eventName = $this->eventName($invitee);
        $date = $this->eventDate($invitee);
        $rsvpLink = $this->rsvpLink($invitee);

        return "Reminder: Dear {$invitee->name}, please confirm your attendance for {$eventName}.\n"
            . "Date: {$date}\n"
            . "RSVP here:\n{$rsvpLink}\n\n"
            . "Serial: {$invitee->serial_number}\n"
            . "eLive Card";
    }

    public function buildAttendingReminderMessage(Invitee $invitee): string
    {
        $invitee->loadMissing(['event', 'cardType']);

        $eventName = $this->eventName($invitee);
        $venue = $this->eventVenue($invitee);
        $date = $this->eventDate($invitee);
        $time = $this->eventTime($invitee);
        $confirmedGuests = $invitee->confirmed_guests ?: $invitee->final_allowed_guests;

        return "Reminder: Dear {$invitee->name}, you confirmed attendance for {$eventName}.\n"
            . "Date: {$date}\n"
            . "Time: {$time}\n"
            . "Venue: {$venue}\n"
            . "Confirmed guests: {$confirmedGuests}\n"
            . "Serial: {$invitee->serial_number}\n\n"
            . "eLive Card";
    }

    public function buildEventDayReminderMessage(Invitee $invitee): string
    {
        $invitee->loadMissing(['event', 'cardType']);

        $eventName = $this->eventName($invitee);
        $venue = $this->eventVenue($invitee);
        $time = $this->eventTime($invitee);
        $mapsLink = $invitee->event?->google_maps_link;

        $message = "Today Reminder: Dear {$invitee->name}, today is {$eventName}.\n"
            . "Time: {$time}\n"
            . "Venue: {$venue}\n"
            . "Serial: {$invitee->serial_number}\n";

        if (filled($mapsLink)) {
            $message .= "Map: {$mapsLink}\n";
        }

        return $message . "\neLive Card";
    }

    protected function eventName(Invitee $invitee): string
    {
        return $invitee->event?->title
            ?? $invitee->event?->name
            ?? $invitee->event?->event_name
            ?? 'your event';
    }

    protected function eventVenue(Invitee $invitee): string
    {
        return $invitee->event?->venue_name
            ?? $invitee->event?->venue
            ?? $invitee->event?->venue_address
            ?? 'the event venue';
    }

    protected function eventDate(Invitee $invitee): string
    {
        $eventDate = $invitee->event?->event_date
            ?? $invitee->event?->date
            ?? null;

        return $eventDate
            ? Carbon::parse($eventDate)->format('d M Y')
            : 'TBA';
    }

    protected function eventTime(Invitee $invitee): string
    {
        $eventTime = $invitee->event?->start_time
            ?? $invitee->event?->event_time
            ?? $invitee->event?->time
            ?? null;

        return $eventTime
            ? Carbon::parse($eventTime)->format('h:i A')
            : 'TBA';
    }

    protected function rsvpLink(Invitee $invitee): string
    {
        if (method_exists($invitee, 'rsvpUrl')) {
            return $invitee->rsvpUrl();
        }

        if (filled($invitee->rsvp_token)) {
            return url('/rsvp/' . $invitee->rsvp_token);
        }

        return route('invitee.page', $invitee->short_code);
    }

    public function formatPhone(?string $phone): string
    {
        $phone = preg_replace('/\D+/', '', (string) $phone);

        if (blank($phone)) {
            throw new \Exception('Invitee phone number is missing.');
        }

        if (str_starts_with($phone, '0')) {
            return '255' . substr($phone, 1);
        }

        if (str_starts_with($phone, '255')) {
            return $phone;
        }

        if (strlen($phone) === 9) {
            return '255' . $phone;
        }

        return $phone;
    }
}