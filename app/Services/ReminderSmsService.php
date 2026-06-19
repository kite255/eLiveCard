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
        ?string $batchId = null,
        ?string $customMessage = null
    ): bool {
        $message = $customMessage
            ? $this->replacePlaceholders($customMessage, $invitee)
            : $this->buildMessage($invitee, SmsLog::TYPE_INVITATION);

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
        ?string $batchId = null,
        ?string $customMessage = null
    ): bool {
        if (($invitee->rsvp_status ?? null) !== Invitee::RSVP_STATUS_PENDING) {
            return false;
        }

        $message = $customMessage
            ? $this->replacePlaceholders($customMessage, $invitee)
            : $this->buildMessage($invitee, SmsLog::TYPE_RSVP_PENDING_REMINDER);

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
        ?string $batchId = null,
        ?string $customMessage = null
    ): bool {
        if (($invitee->rsvp_status ?? null) !== Invitee::RSVP_STATUS_ATTENDING) {
            return false;
        }

        $message = $customMessage
            ? $this->replacePlaceholders($customMessage, $invitee)
            : $this->buildMessage($invitee, SmsLog::TYPE_ATTENDING_REMINDER);

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
        ?string $batchId = null,
        ?string $customMessage = null
    ): bool {
        $message = $customMessage
            ? $this->replacePlaceholders($customMessage, $invitee)
            : $this->buildMessage($invitee, SmsLog::TYPE_EVENT_DAY_REMINDER);

        return $this->sendSms(
            invitee: $invitee,
            message: $message,
            smsType: SmsLog::TYPE_EVENT_DAY_REMINDER,
            sendSource: $sendSource,
            batchId: $batchId
        );
    }

    /**
     * Send any custom SMS message to one invitee.
     * Useful for Filament modal actions where admin types message manually.
     */
    public function sendCustomSms(
        Invitee $invitee,
        string $message,
        string $sendSource = SmsLog::SOURCE_MANUAL,
        ?string $batchId = null
    ): bool {
        return $this->sendSms(
            invitee: $invitee,
            message: $this->replacePlaceholders($message, $invitee),
            smsType: defined(SmsLog::class . '::TYPE_CUSTOM') ? SmsLog::TYPE_CUSTOM : 'custom',
            sendSource: $sendSource,
            batchId: $batchId
        );
    }

    public function sendBulkInvitationSms($invitees, string $sendSource = SmsLog::SOURCE_BULK_MANUAL, ?string $customMessage = null): array
    {
        return $this->sendBulk($invitees, SmsLog::TYPE_INVITATION, $sendSource, $customMessage);
    }

    public function sendBulkRsvpPendingReminders($invitees, string $sendSource = SmsLog::SOURCE_BULK_MANUAL, ?string $customMessage = null): array
    {
        return $this->sendBulk($invitees, SmsLog::TYPE_RSVP_PENDING_REMINDER, $sendSource, $customMessage);
    }

    public function sendBulkAttendingReminders($invitees, string $sendSource = SmsLog::SOURCE_BULK_MANUAL, ?string $customMessage = null): array
    {
        return $this->sendBulk($invitees, SmsLog::TYPE_ATTENDING_REMINDER, $sendSource, $customMessage);
    }

    public function sendBulkEventDayReminders($invitees, string $sendSource = SmsLog::SOURCE_BULK_MANUAL, ?string $customMessage = null): array
    {
        return $this->sendBulk($invitees, SmsLog::TYPE_EVENT_DAY_REMINDER, $sendSource, $customMessage);
    }

    public function sendBulkCustomSms($invitees, string $message, string $sendSource = SmsLog::SOURCE_BULK_MANUAL): array
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

            $success = $this->sendCustomSms(
                invitee: $invitee,
                message: $message,
                sendSource: $sendSource,
                batchId: $batchId
            );

            $success ? $sent++ : $failed++;
        }

        return compact('sent', 'failed', 'skipped', 'batchId') + ['batch_id' => $batchId];
    }

    protected function sendBulk($invitees, string $smsType, string $sendSource, ?string $customMessage = null): array
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
                SmsLog::TYPE_INVITATION => $this->sendInvitationSms($invitee, $sendSource, $batchId, $customMessage),
                SmsLog::TYPE_RSVP_PENDING_REMINDER => $this->sendRsvpPendingReminder($invitee, $sendSource, $batchId, $customMessage),
                SmsLog::TYPE_ATTENDING_REMINDER => $this->sendAttendingReminder($invitee, $sendSource, $batchId, $customMessage),
                SmsLog::TYPE_EVENT_DAY_REMINDER => $this->sendEventDayReminder($invitee, $sendSource, $batchId, $customMessage),
                default => false,
            };

            $success ? $sent++ : $failed++;
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
        $message = trim($message);

        if ($message === '') {
            $this->markInviteeSmsFailed($invitee, $smsType, 'SMS message is empty.');
            return false;
        }

        $smsLog = SmsLog::create([
            'event_id' => $invitee->event_id,
            'invitee_id' => $invitee->id,
            'phone' => $invitee->phone,
            'sms_type' => $smsType,
            'send_source' => $sendSource,
            'sent_by_user_id' => Auth::id(),
            'batch_id' => $batchId,
            'message' => $message,
            'status' => SmsLog::STATUS_PENDING,
            'provider' => config('sms.provider', config('services.sms.provider', 'default')),
        ]);

        try {
            $response = app(SmsService::class)->send($invitee->phone, $message);
            $messageId = $this->extractMessageId($response);

            $smsLog->update([
                'status' => SmsLog::STATUS_SENT,
                'provider_message_id' => $messageId,
                'provider_response' => $this->normalizeProviderResponse($response),
                'sent_at' => now(),
                'failed_at' => null,
                'error_message' => null,
            ]);

            $this->markInviteeSmsSent($invitee, $smsType, $messageId);

            Log::info('Reminder SMS sent', [
                'event_id' => $invitee->event_id,
                'invitee_id' => $invitee->id,
                'sms_log_id' => $smsLog->id,
                'sms_type' => $smsType,
                'send_source' => $sendSource,
                'batch_id' => $batchId,
                'phone' => $invitee->phone,
                'provider_message_id' => $messageId,
            ]);

            return true;
        } catch (\Throwable $e) {
            $smsLog->update([
                'status' => SmsLog::STATUS_FAILED,
                'provider_response' => [
                    'error' => $e->getMessage(),
                ],
                'error_message' => $e->getMessage(),
                'failed_at' => now(),
            ]);

            $this->markInviteeSmsFailed($invitee, $smsType, $e->getMessage());

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
            ->orderByRaw('CASE WHEN event_id = ? THEN 0 ELSE 1 END', [$invitee->event_id])
            ->orderByDesc('is_default')
            ->latest()
            ->first();

        $message = $template?->message ?: $this->defaultTemplateMessage($smsType);

        return $this->replacePlaceholders($message, $invitee);
    }

    protected function defaultTemplateMessage(string $smsType): string
    {
        return match ($smsType) {
            SmsLog::TYPE_INVITATION => 'Dear {name}, you are invited to {event_name} on {event_date} at {venue}. Time: {event_time}. Serial: {serial_number}. Guests: {guest_count}. View card: {private_url}',
            SmsLog::TYPE_RSVP_PENDING_REMINDER => 'Dear {name}, kindly confirm your attendance for {event_name}. Serial: {serial_number}. RSVP here: {rsvp_link}',
            SmsLog::TYPE_ATTENDING_REMINDER => 'Dear {name}, reminder: {event_name} is tomorrow at {venue}. Time: {event_time}. Table: {table_number}. Serial: {serial_number}.',
            SmsLog::TYPE_EVENT_DAY_REMINDER => 'Dear {name}, {event_name} is today at {venue}. Time: {event_time}. Serial: {serial_number}. Map: {google_maps_link}',
            default => 'Dear {name}, you have a message from {event_name}. Serial: {serial_number}. Link: {private_url}',
        };
    }

    protected function replacePlaceholders(string $message, Invitee $invitee): string
    {
        $invitee->loadMissing(['event', 'cardType']);

        $event = $invitee->event;
        $cardType = $invitee->cardType;

        $eventName = $event?->title ?? $event?->name ?? 'the event';
        $eventType = $event?->event_type ?? '';

        $eventDate = $this->formatDate($event?->event_date ?? $event?->date ?? null);
        $eventTime = $this->formatTime($event?->start_time ?? $event?->time ?? null);
        $eventEndTime = $this->formatTime($event?->end_time ?? null);

        $venueName = $event?->venue_name ?? $event?->venue ?? '';
        $venueAddress = $event?->venue_address ?? $event?->address ?? '';
        $venue = $venueName ?: $venueAddress ?: 'the venue';

        $privateUrl = $invitee->private_invitation_url ?? '';
        $cardUrl = $invitee->generated_card_url ?? $privateUrl;
        $rsvpLink = $privateUrl;
        $locationLink = $event?->google_maps_link ?? '';

        $guestCount = $invitee->final_allowed_guests
            ?? $invitee->allowed_guests
            ?? $cardType?->allowed_people
            ?? 1;

        $values = [
            'name' => $invitee->name ?? '',
            'invitee_name' => $invitee->name ?? '',
            'phone' => $invitee->phone ?? '',
            'event_name' => $eventName,
            'event_type' => $eventType,
            'event_date' => $eventDate,
            'date' => $eventDate,
            'event_time' => $eventTime,
            'time' => $eventTime,
            'event_end_time' => $eventEndTime,
            'venue' => $venue,
            'event_venue' => $venue,
            'venue_name' => $venueName,
            'venue_address' => $venueAddress,
            'google_maps_link' => $locationLink,
            'location_link' => $locationLink,
            'dress_code' => $event?->dress_code ?? '',
            'program' => $event?->program ?? '',
            'contact_person_name' => $event?->contact_person_name ?? '',
            'contact_person_phone' => $event?->contact_person_phone ?? '',
            'card_type' => $cardType?->name ?? '',
            'guest_count' => (string) $guestCount,
            'allowed_guests' => (string) $guestCount,
            'category' => $invitee->category ?? '',
            'table_number' => $invitee->table_number ?? '',
            'serial_number' => $invitee->serial_number ?? '',
            'short_code' => $invitee->short_code ?? '',
            'private_url' => $privateUrl,
            'private_link' => $privateUrl,
            'invitation_link' => $privateUrl,
            'rsvp_link' => $rsvpLink,
            'card_link' => $cardUrl,
            'qr_code_url' => $invitee->qr_code_url ?? '',
        ];

        foreach ($values as $key => $value) {
            $message = str_replace(
                [
                    '{' . $key . '}',
                    '{{' . $key . '}}',
                    '#' . strtoupper($key) . '#',
                ],
                (string) $value,
                $message
            );
        }

        return trim(preg_replace('/\s+/', ' ', $message) ?? $message);
    }

    protected function markInviteeSmsSent(Invitee $invitee, string $smsType, ?string $messageId = null): void
    {
        if (method_exists($invitee, 'updateSmsStatusByType')) {
            $invitee->updateSmsStatusByType(
                smsType: $smsType,
                status: Invitee::SMS_STATUS_SENT,
                messageId: $messageId,
                error: null
            );

            return;
        }

        $invitee->forceFill([
            'reminder_sms_status' => Invitee::SMS_STATUS_SENT,
            'reminder_sms_sent_at' => now(),
            'reminder_sms_error' => null,
        ])->save();
    }

    protected function markInviteeSmsFailed(Invitee $invitee, string $smsType, string $error): void
    {
        if (method_exists($invitee, 'updateSmsStatusByType')) {
            $invitee->updateSmsStatusByType(
                smsType: $smsType,
                status: Invitee::SMS_STATUS_FAILED,
                messageId: null,
                error: $error
            );

            return;
        }

        $invitee->forceFill([
            'reminder_sms_status' => Invitee::SMS_STATUS_FAILED,
            'reminder_sms_error' => $error,
        ])->save();
    }

    protected function extractMessageId(mixed $response): ?string
    {
        if (is_array($response)) {
            return $response['message_id']
                ?? $response['messageId']
                ?? $response['shoot_id']
                ?? $response['shootId']
                ?? $response['id']
                ?? $response['sms_id']
                ?? $response['smsId']
                ?? $response['data']['message_id']
                ?? $response['data']['messageId']
                ?? $response['data']['shoot_id']
                ?? $response['data']['shootId']
                ?? $response['response']['data']['shootId']
                ?? null;
        }

        if (is_object($response)) {
            return $response->message_id
                ?? $response->messageId
                ?? $response->shoot_id
                ?? $response->shootId
                ?? $response->id
                ?? null;
        }

        return is_string($response) ? $response : null;
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
            return ['message_id' => $response];
        }

        return ['response' => $response];
    }

    protected function formatDate(mixed $value): string
    {
        if (! $value) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d M Y');
        }

        try {
            return \Carbon\Carbon::parse($value)->format('d M Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    protected function formatTime(mixed $value): string
    {
        if (! $value) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        try {
            return \Carbon\Carbon::parse($value)->format('H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
