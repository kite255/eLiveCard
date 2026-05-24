<?php

namespace App\Services;

use App\Models\Invitee;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReminderSmsService
{
    public function sendInvitationSms(
        Invitee $invitee,
        string $sendSource = SmsLog::SOURCE_MANUAL,
        ?string $batchId = null
    ): bool {
        $message = $this->buildMessage($invitee, SmsLog::TYPE_INVITATION);

        return $this->sendSms(
            invitee: $invitee,
            message: $message,
            smsType: SmsLog::TYPE_INVITATION,
            sendSource: $sendSource,
            batchId: $batchId
        );
    }

    public function sendRsvpPendingReminder(
        Invitee $invitee,
        string $sendSource = SmsLog::SOURCE_MANUAL,
        ?string $batchId = null
    ): bool {
        if ($invitee->rsvp_status !== Invitee::RSVP_STATUS_PENDING) {
            return false;
        }

        $message = $this->buildMessage($invitee, SmsLog::TYPE_RSVP_PENDING_REMINDER);

        return $this->sendSms(
            invitee: $invitee,
            message: $message,
            smsType: SmsLog::TYPE_RSVP_PENDING_REMINDER,
            sendSource: $sendSource,
            batchId: $batchId
        );
    }

    public function sendAttendingReminder(
        Invitee $invitee,
        string $sendSource = SmsLog::SOURCE_MANUAL,
        ?string $batchId = null
    ): bool {
        if ($invitee->rsvp_status !== Invitee::RSVP_STATUS_ATTENDING) {
            return false;
        }

        $message = $this->buildMessage($invitee, SmsLog::TYPE_ATTENDING_REMINDER);

        return $this->sendSms(
            invitee: $invitee,
            message: $message,
            smsType: SmsLog::TYPE_ATTENDING_REMINDER,
            sendSource: $sendSource,
            batchId: $batchId
        );
    }

    public function sendEventDayReminder(
        Invitee $invitee,
        string $sendSource = SmsLog::SOURCE_MANUAL,
        ?string $batchId = null
    ): bool {
        $message = $this->buildMessage($invitee, SmsLog::TYPE_EVENT_DAY_REMINDER);

        return $this->sendSms(
            invitee: $invitee,
            message: $message,
            smsType: SmsLog::TYPE_EVENT_DAY_REMINDER,
            sendSource: $sendSource,
            batchId: $batchId
        );
    }

    public function sendBulkInvitationSms(
        $invitees,
        string $sendSource = SmsLog::SOURCE_BULK_MANUAL
    ): array {
        return $this->sendBulk($invitees, SmsLog::TYPE_INVITATION, $sendSource);
    }

    public function sendBulkRsvpPendingReminders(
        $invitees,
        string $sendSource = SmsLog::SOURCE_BULK_MANUAL
    ): array {
        return $this->sendBulk($invitees, SmsLog::TYPE_RSVP_PENDING_REMINDER, $sendSource);
    }

    public function sendBulkAttendingReminders(
        $invitees,
        string $sendSource = SmsLog::SOURCE_BULK_MANUAL
    ): array {
        return $this->sendBulk($invitees, SmsLog::TYPE_ATTENDING_REMINDER, $sendSource);
    }

    public function sendBulkEventDayReminders(
        $invitees,
        string $sendSource = SmsLog::SOURCE_BULK_MANUAL
    ): array {
        return $this->sendBulk($invitees, SmsLog::TYPE_EVENT_DAY_REMINDER, $sendSource);
    }

    protected function sendBulk($invitees, string $smsType, string $sendSource): array
    {
        $sent = 0;
        $failed = 0;
        $skipped = 0;

        $batchId = (string) Str::uuid();

        foreach ($invitees as $invitee) {
            if (! $invitee instanceof Invitee) {
                $skipped++;
                continue;
            }

            $success = match ($smsType) {
                SmsLog::TYPE_INVITATION => $this->sendInvitationSms(
                    invitee: $invitee,
                    sendSource: $sendSource,
                    batchId: $batchId
                ),

                SmsLog::TYPE_RSVP_PENDING_REMINDER => $this->sendRsvpPendingReminder(
                    invitee: $invitee,
                    sendSource: $sendSource,
                    batchId: $batchId
                ),

                SmsLog::TYPE_ATTENDING_REMINDER => $this->sendAttendingReminder(
                    invitee: $invitee,
                    sendSource: $sendSource,
                    batchId: $batchId
                ),

                SmsLog::TYPE_EVENT_DAY_REMINDER => $this->sendEventDayReminder(
                    invitee: $invitee,
                    sendSource: $sendSource,
                    batchId: $batchId
                ),

                default => false,
            };

            if ($success) {
                $sent++;
            } else {
                $failed++;
            }
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            'batch_id' => $batchId,
        ];
    }

    protected function sendSms(
        Invitee $invitee,
        string $message,
        string $smsType,
        string $sendSource = SmsLog::SOURCE_MANUAL,
        ?string $batchId = null
    ): bool {
        $batchId ??= (string) Str::uuid();

        $smsLog = SmsLog::create([
            'event_id' => $invitee->event_id,
            'invitee_id' => $invitee->id,
            'phone' => $invitee->phone,
            'sms_type' => $smsType,

            // Audit fields
            'send_source' => $sendSource,
            'sent_by_user_id' => Auth::id(),
            'batch_id' => $batchId,

            'message' => $message,
            'status' => SmsLog::STATUS_PENDING,
            'provider' => config('sms.provider', 'default'),
        ]);

        try {
            $response = app(SmsService::class)->send($invitee->phone, $message);

            $messageId = $this->extractMessageId($response);

            $smsLog->update([
                'status' => SmsLog::STATUS_SENT,
                'provider_message_id' => $messageId,
                'provider_response' => $this->normalizeProviderResponse($response),
                'sent_at' => now(),
                'error_message' => null,
            ]);

            $invitee->updateSmsStatusByType(
                smsType: $smsType,
                status: Invitee::SMS_STATUS_SENT,
                messageId: $messageId,
                error: null
            );

            return true;
        } catch (\Throwable $e) {
            $smsLog->update([
                'status' => SmsLog::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'failed_at' => now(),
            ]);

            $invitee->updateSmsStatusByType(
                smsType: $smsType,
                status: Invitee::SMS_STATUS_FAILED,
                messageId: null,
                error: $e->getMessage()
            );

            Log::error('Reminder SMS failed', [
                'event_id' => $invitee->event_id,
                'invitee_id' => $invitee->id,
                'sms_log_id' => $smsLog->id,
                'sms_type' => $smsType,
                'send_source' => $sendSource,
                'batch_id' => $batchId,
                'phone' => $invitee->phone,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function buildMessage(Invitee $invitee, string $smsType): string
    {
        $template = SmsTemplate::query()
            ->where('sms_type', $smsType)
            ->where('is_active', true)
            ->where(function ($query) use ($invitee) {
                $query
                    ->where('event_id', $invitee->event_id)
                    ->orWhereNull('event_id');
            })
            ->orderByRaw(
                'CASE WHEN event_id = ? THEN 0 ELSE 1 END',
                [$invitee->event_id]
            )
            ->orderByDesc('is_default')
            ->latest()
            ->first();

        $message = $template?->message ?? $this->defaultTemplateMessage($smsType);

        return $this->replacePlaceholders($message, $invitee);
    }

    protected function defaultTemplateMessage(string $smsType): string
    {
        return match ($smsType) {
            SmsLog::TYPE_INVITATION => 'Dear {name}, you are invited to {event_name} on {event_date} at {venue}. Time: {event_time}. Serial: {serial_number}. Guests: {guest_count}. RSVP: {private_url}',

            SmsLog::TYPE_RSVP_PENDING_REMINDER => 'Dear {name}, kindly confirm your attendance for {event_name}. Serial: {serial_number}. RSVP here: {rsvp_link}',

            SmsLog::TYPE_ATTENDING_REMINDER => 'Dear {name}, reminder: {event_name} is tomorrow at {venue}. Time: {event_time}. Table: {table_number}. Serial: {serial_number}.',

            SmsLog::TYPE_EVENT_DAY_REMINDER => 'Dear {name}, {event_name} is today at {venue}. Serial: {serial_number}. Map: {google_maps_link}',

            default => 'Dear {name}, you have a message from {event_name}. Serial: {serial_number}.',
        };
    }

    protected function replacePlaceholders(string $message, Invitee $invitee): string
    {
        $invitee->loadMissing(['event', 'cardType']);

        $event = $invitee->event;

        $eventName = $event?->title ?? 'the event';
        $eventType = $event?->event_type ?? '';

        $eventDate = $event?->event_date
            ? $event->event_date->format('d M Y')
            : '';

        $eventTime = $event?->start_time
            ? $event->start_time->format('H:i')
            : '';

        $eventEndTime = $event?->end_time
            ? $event->end_time->format('H:i')
            : '';

        $venueName = $event?->venue_name ?? '';
        $venueAddress = $event?->venue_address ?? '';
        $venue = $venueName ?: $venueAddress ?: 'the venue';

        $googleMapsLink = $event?->google_maps_link ?? '';
        $dressCode = $event?->dress_code ?? '';
        $program = $event?->program ?? '';

        $contactPersonName = $event?->contact_person_name ?? '';
        $contactPersonPhone = $event?->contact_person_phone ?? '';

        $cardType = $invitee->cardType?->name ?? '';

        $guestCount = $invitee->final_allowed_guests
            ?? $invitee->allowed_guests
            ?? 1;

        $allowedGuests = $guestCount;

        $category = $invitee->category ?? '';
        $tableNumber = $invitee->table_number ?? '';
        $serialNumber = $invitee->serial_number ?? '';
        $shortCode = $invitee->short_code ?? '';

        $privateUrl = $invitee->private_invitation_url ?? '';
        $rsvpLink = $privateUrl;
        $qrCodeUrl = $invitee->qr_code_url ?? '';

        $message = str_replace(
            [
                '{name}',
                '{phone}',
                '{event_name}',
                '{event_type}',
                '{event_date}',
                '{event_time}',
                '{event_end_time}',
                '{venue}',
                '{venue_name}',
                '{venue_address}',
                '{google_maps_link}',
                '{dress_code}',
                '{program}',
                '{contact_person_name}',
                '{contact_person_phone}',
                '{card_type}',
                '{guest_count}',
                '{allowed_guests}',
                '{category}',
                '{table_number}',
                '{serial_number}',
                '{short_code}',
                '{private_url}',
                '{rsvp_link}',
                '{qr_code_url}',
            ],
            [
                $invitee->name,
                $invitee->phone,
                $eventName,
                $eventType,
                $eventDate,
                $eventTime,
                $eventEndTime,
                $venue,
                $venueName,
                $venueAddress,
                $googleMapsLink,
                $dressCode,
                $program,
                $contactPersonName,
                $contactPersonPhone,
                $cardType,
                $guestCount,
                $allowedGuests,
                $category,
                $tableNumber,
                $serialNumber,
                $shortCode,
                $privateUrl,
                $rsvpLink,
                $qrCodeUrl,
            ],
            $message
        );

        return trim(preg_replace('/\s+/', ' ', $message));
    }

    protected function extractMessageId(mixed $response): ?string
    {
        if (is_array($response)) {
            return $response['message_id']
                ?? $response['shoot_id']
                ?? $response['id']
                ?? $response['sms_id']
                ?? $response['data']['message_id']
                ?? $response['data']['shootId']
                ?? $response['response']['data']['shootId']
                ?? null;
        }

        if (is_string($response)) {
            return $response;
        }

        if (is_object($response) && isset($response->message_id)) {
            return $response->message_id;
        }

        return null;
    }

    protected function normalizeProviderResponse(mixed $response): array
    {
        if (is_array($response)) {
            return $response;
        }

        if (is_object($response)) {
            return json_decode(json_encode($response), true) ?: [];
        }

        if (is_string($response)) {
            return [
                'message_id' => $response,
            ];
        }

        return [
            'response' => $response,
        ];
    }
}