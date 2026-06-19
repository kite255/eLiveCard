<?php

namespace App\Services;

use App\Models\Invitee;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class SmsService
{
    /**
     * Send the main invitation/private card link SMS.
     */
    public function sendInvitation(Invitee $invitee, ?string $customMessage = null): array
    {
        $invitee->loadMissing(['event', 'cardType']);

        $message = filled($customMessage)
            ? $this->replacePlaceholders($customMessage, $invitee)
            : $this->buildInvitationMessage($invitee);

        return $this->sendToInvitee(
            invitee: $invitee,
            message: $message,
            type: 'invitation_card',
            successCallback: 'markInvitationSmsAsSent',
            failedCallback: 'markInvitationSmsAsFailed',
        );
    }

    /**
     * Alias used by Filament actions that send the invitee private card page.
     */
    public function sendCardLink(Invitee $invitee, ?string $customMessage = null): array
    {
        return $this->sendInvitation($invitee, $customMessage);
    }

    /**
     * Send RSVP pending reminder SMS.
     */
    public function sendRsvpPendingReminder(Invitee $invitee, ?string $customMessage = null): array
    {
        $invitee->loadMissing(['event', 'cardType']);

        return $this->sendToInvitee(
            invitee: $invitee,
            message: filled($customMessage)
                ? $this->replacePlaceholders($customMessage, $invitee)
                : $this->buildRsvpPendingReminderMessage($invitee),
            type: 'rsvp_pending_reminder',
            successCallback: 'markReminderSmsAsSent',
            failedCallback: 'markReminderSmsAsFailed',
        );
    }

    /**
     * Send reminder to invitees who already confirmed attendance.
     */
    public function sendAttendingReminder(Invitee $invitee, ?string $customMessage = null): array
    {
        $invitee->loadMissing(['event', 'cardType']);

        return $this->sendToInvitee(
            invitee: $invitee,
            message: filled($customMessage)
                ? $this->replacePlaceholders($customMessage, $invitee)
                : $this->buildAttendingReminderMessage($invitee),
            type: 'attending_reminder',
            successCallback: 'markReminderSmsAsSent',
            failedCallback: 'markReminderSmsAsFailed',
        );
    }

    /**
     * Send event-day final reminder SMS.
     */
    public function sendEventDayReminder(Invitee $invitee, ?string $customMessage = null): array
    {
        $invitee->loadMissing(['event', 'cardType']);

        return $this->sendToInvitee(
            invitee: $invitee,
            message: filled($customMessage)
                ? $this->replacePlaceholders($customMessage, $invitee)
                : $this->buildEventDayReminderMessage($invitee),
            type: 'event_day_reminder',
            successCallback: 'markFinalSmsAsSent',
            failedCallback: 'markFinalSmsAsFailed',
        );
    }

    /**
     * Generic custom SMS method for admin actions and message templates.
     */
    public function sendCustomMessage(Invitee $invitee, string $message, string $type = 'custom_sms'): array
    {
        $invitee->loadMissing(['event', 'cardType']);

        return $this->sendToInvitee(
            invitee: $invitee,
            message: $this->replacePlaceholders($message, $invitee),
            type: $type,
        );
    }

    protected function sendToInvitee(
        Invitee $invitee,
        string $message,
        string $type,
        ?string $successCallback = null,
        ?string $failedCallback = null,
    ): array {
        $invitee->loadMissing(['event', 'cardType']);

        $phone = $this->formatPhone($invitee->phone);
        $message = trim($message);
        $reference = 'elive_' . $type . '_' . $invitee->id . '_' . Str::upper(Str::random(8));

        if (blank($message)) {
            throw new \Exception('SMS message is empty.');
        }

        try {
            $response = $this->send(
                phone: $phone,
                message: $message,
                reference: $reference,
            );

            $messageId = $this->extractMessageId($response, $reference);
            $isLogDriver = ($response['driver'] ?? null) === 'log';
            $status = $isLogDriver ? 'logged' : 'sent';

            if ($isLogDriver) {
                $this->markInviteeSmsAsLogged($invitee, $messageId, $type);
            } else {
                $this->markInviteeSmsAsSent($invitee, $messageId, $successCallback, $type);
            }

            $freshInvitee = $invitee->fresh(['event', 'cardType']) ?? $invitee;

            $this->recordCommunicationLog(
                invitee: $freshInvitee,
                type: $type,
                channel: 'sms',
                recipient: $phone,
                message: $message,
                status: $status,
                providerMessageId: $messageId,
                errorMessage: null,
                response: $response,
                provider: $response['provider'] ?? $this->smsProvider(),
            );

            return array_merge($response, [
                'success' => true,
                'status' => $status,
                'type' => $type,
                'channel' => 'sms',
                'recipient' => $phone,
                'message_id' => $messageId,
                'notification_title' => $isLogDriver ? 'SMS logged' : 'SMS sent',
                'notification_body' => $isLogDriver
                    ? 'SMS was logged only because SMS_DRIVER=log. Message ID: ' . $messageId
                    : 'SMS sent successfully. Message ID: ' . $messageId,
            ]);
        } catch (Throwable $e) {
            $this->markInviteeSmsAsFailed($invitee, $e->getMessage(), $failedCallback, $type);

            $this->recordCommunicationLog(
                invitee: $invitee,
                type: $type,
                channel: 'sms',
                recipient: $phone,
                message: $message,
                status: 'failed',
                providerMessageId: $reference,
                errorMessage: $e->getMessage(),
                response: null,
                provider: $this->smsProvider(),
            );

            Log::error('eLive Card SMS failed', [
                'invitee_id' => $invitee->id,
                'event_id' => $invitee->event_id,
                'phone' => $phone,
                'type' => $type,
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 'failed',
                'type' => $type,
                'channel' => 'sms',
                'recipient' => $phone,
                'message_id' => $reference,
                'error' => $e->getMessage(),
                'notification_title' => 'SMS failed',
                'notification_body' => $e->getMessage(),
            ];
        }
    }

    /**
     * Low-level SMS sender. Uses log driver for local testing and http driver for real sending.
     */
    public function send(string $phone, string $message, ?string $reference = null): array
    {
        $driver = config('services.sms.driver', env('SMS_DRIVER', 'log'));
        $provider = $this->smsProvider();

        if ($driver === 'log') {
            Log::info('eLive Card SMS logged only', [
                'to' => $phone,
                'message' => $message,
                'reference' => $reference,
            ]);

            return [
                'success' => true,
                'driver' => 'log',
                'provider' => $provider,
                'message_id' => $reference,
                'provider_message' => 'SMS logged only. Set SMS_DRIVER=http to send real SMS.',
                'response' => null,
            ];
        }

        if ($driver !== 'http') {
            throw new \Exception('Unsupported SMS driver: ' . $driver);
        }

        $apiUrl = $this->resolveSmsApiUrl();
        $apiKey = config('services.sms.api_key', env('SMS_API_KEY'));
        $apiSecret = config('services.sms.api_secret', env('SMS_API_SECRET'));
        $senderId = config('services.sms.sender_id', env('SMS_SENDER_ID', 'eLiveCard'));
        $timeout = (int) config('services.sms.timeout', env('SMS_TIMEOUT', 30));

        if (blank($apiUrl)) {
            throw new \Exception('SMS_API_URL is not configured.');
        }

        if (blank($apiKey)) {
            throw new \Exception('SMS_API_KEY is not configured.');
        }

        if (blank($apiSecret)) {
            throw new \Exception('SMS_API_SECRET is not configured.');
        }

        if (blank($senderId)) {
            throw new \Exception('SMS_SENDER_ID is not configured.');
        }

        $payload = $this->smsPayload(
            senderId: (string) $senderId,
            phone: $phone,
            message: $message,
            reference: $reference,
        );

        Log::info('Sending eLive Card SMS request', [
            'url' => $apiUrl,
            'provider' => $provider,
            'senderId' => $senderId,
            'contacts' => $phone,
            'reference' => $reference,
        ]);

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->asJson()
            ->withHeaders($this->smsAuthHeaders((string) $apiKey, (string) $apiSecret))
            ->post($apiUrl, $payload);

        $body = $response->body();
        $data = $response->json() ?? [];

        Log::info('eLive Card SMS provider response', [
            'status' => $response->status(),
            'json' => $data,
            'body' => Str::limit($body, 1000),
            'reference' => $reference,
        ]);

        if ($response->failed()) {
            throw new \Exception('SMS sending failed. HTTP ' . $response->status() . ': ' . Str::limit($body, 500));
        }

        if (! $this->providerAccepted($data)) {
            throw new \Exception('SMS provider rejected the message: ' . Str::limit(json_encode($data), 500));
        }

        return [
            'success' => true,
            'driver' => 'http',
            'provider' => $provider,
            'shoot_id' => data_get($data, 'data.shootId'),
            'message_id' => data_get($data, 'data.shootId')
                ?? data_get($data, 'data.messageId')
                ?? data_get($data, 'message_id')
                ?? data_get($data, 'id')
                ?? $reference,
            'valid_contacts' => data_get($data, 'data.validContacts'),
            'invalid_contacts' => data_get($data, 'data.invalidContacts'),
            'duplicated_contacts' => data_get($data, 'data.duplicatedContacts'),
            'message_size' => data_get($data, 'data.messageSize'),
            'provider_message' => data_get($data, 'message'),
            'response' => $data,
        ];
    }

    /**
     * Provider payload. This includes messageType because the live gateway rejected requests without it.
     */
    protected function smsPayload(string $senderId, string $phone, string $message, ?string $reference = null): array
    {
        return array_filter([
            'senderId' => $senderId,
            'messageType' => 'text',
            'type' => 'text',
            'contacts' => $phone,
            'message' => $message,
            'reference' => $reference,
        ], static fn ($value) => filled($value));
    }

    /**
     * Resolve SMS endpoint from either SMS_API_URL or SMS_BASE_URL.
     */
    protected function resolveSmsApiUrl(): ?string
    {
        $apiUrl = config('services.sms.api_url') ?: env('SMS_API_URL');

        if (filled($apiUrl)) {
            return rtrim((string) $apiUrl, '/');
        }

        $baseUrl = config('services.sms.base_url')
            ?: env('SMS_BASE_URL')
            ?: env('ELIVE_SMS_BASE_URL');

        if (blank($baseUrl)) {
            return null;
        }

        return rtrim((string) $baseUrl, '/') . '/api/v1/vendor/message/send';
    }

    /**
     * Send supported auth header styles because deployments may use different names.
     */
    protected function smsAuthHeaders(string $apiKey, string $apiSecret): array
    {
        return [
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'api-key' => $apiKey,
            'api-secret' => $apiSecret,
            'Api-Key' => $apiKey,
            'Api-Secret' => $apiSecret,
            'X-API-KEY' => $apiKey,
            'X-API-SECRET' => $apiSecret,
            'Content-Type' => 'application/json',
        ];
    }

    protected function providerAccepted(array $data): bool
    {
        $success = $data['success'] ?? null;
        $status = Str::lower((string) ($data['status'] ?? ''));
        $message = Str::lower((string) ($data['message'] ?? ''));
        $code = (string) ($data['code'] ?? data_get($data, 'data.code', ''));

        if ($success === true || $success === 1 || $success === 'true' || $success === '1') {
            return true;
        }

        if (in_array($status, ['success', 'sent', 'queued', 'ok', 'accepted'], true)) {
            return true;
        }

        if (in_array($code, ['200', '201', '202'], true)) {
            return true;
        }

        if (str_contains($message, 'success') || str_contains($message, 'queued') || str_contains($message, 'accepted')) {
            return true;
        }

        return filled(data_get($data, 'data.shootId'))
            || filled(data_get($data, 'data.messageId'));
    }

    protected function extractMessageId(array $response, string $reference): string
    {
        return (string) (
            $response['shoot_id']
            ?? $response['message_id']
            ?? data_get($response, 'response.data.shootId')
            ?? data_get($response, 'response.data.messageId')
            ?? $reference
        );
    }

    protected function smsProvider(): string
    {
        return (string) config('services.sms.provider', env('SMS_PROVIDER', 'sms'));
    }

    protected function markInviteeSmsAsLogged(Invitee $invitee, string $messageId, string $type): void
    {
        $updates = [
            'message_status' => 'logged',
            'last_message_channel' => 'sms',
            'sms_status' => 'logged',
            'sms_message_id' => $messageId,
        ];

        $updates = array_merge($updates, $this->typeStatusUpdates($type, 'logged', $messageId));

        $this->safeUpdateInvitee($invitee, $updates);
    }

    protected function markInviteeSmsAsSent(Invitee $invitee, string $messageId, ?string $callback = null, ?string $type = null): void
    {
        if ($callback && method_exists($invitee, $callback)) {
            $invitee->{$callback}($messageId);
            $invitee->refresh();
            return;
        }

        if (method_exists($invitee, 'markSmsAsSent')) {
            $invitee->markSmsAsSent($messageId);
            $invitee->refresh();
            return;
        }

        $updates = [
            'message_status' => 'sent',
            'last_message_channel' => 'sms',
            'sms_status' => 'sent',
            'sms_message_id' => $messageId,
            'sent_at' => now(),
            'sms_sent_at' => now(),
        ];

        $updates = array_merge($updates, $this->typeStatusUpdates($type, 'sent', $messageId));

        $this->safeUpdateInvitee($invitee, $updates);
    }

    protected function markInviteeSmsAsFailed(Invitee $invitee, string $error, ?string $callback = null, ?string $type = null): void
    {
        if ($callback && method_exists($invitee, $callback)) {
            $invitee->{$callback}($error);
            $invitee->refresh();
            return;
        }

        if (method_exists($invitee, 'markSmsAsFailed')) {
            $invitee->markSmsAsFailed($error);
            $invitee->refresh();
            return;
        }

        $updates = [
            'message_status' => 'failed',
            'sms_status' => 'failed',
            'sms_error' => $error,
        ];

        $updates = array_merge($updates, $this->typeStatusUpdates($type, 'failed', null, $error));

        $this->safeUpdateInvitee($invitee, $updates);
    }

    protected function typeStatusUpdates(?string $type, string $status, ?string $messageId = null, ?string $error = null): array
    {
        $now = now();

        return match ($type) {
            'invitation_card' => [
                'invitation_sms_status' => $status,
                'invitation_sms_message_id' => $messageId,
                'invitation_sms_error' => $error,
                'invitation_sms_sent_at' => in_array($status, ['sent', 'logged'], true) ? $now : null,
            ],
            'rsvp_pending_reminder', 'attending_reminder' => [
                'reminder_sms_status' => $status,
                'reminder_sms_message_id' => $messageId,
                'reminder_sms_error' => $error,
                'reminder_sms_sent_at' => in_array($status, ['sent', 'logged'], true) ? $now : null,
            ],
            'event_day_reminder' => [
                'final_sms_status' => $status,
                'final_sms_message_id' => $messageId,
                'final_sms_error' => $error,
                'final_sms_sent_at' => in_array($status, ['sent', 'logged'], true) ? $now : null,
            ],
            default => [],
        };
    }

    protected function safeUpdateInvitee(Invitee $invitee, array $updates): void
    {
        $filtered = [];

        foreach ($updates as $column => $value) {
            if (Schema::hasColumn($invitee->getTable(), $column)) {
                $filtered[$column] = $value;
            }
        }

        if ($filtered !== []) {
            $invitee->forceFill($filtered)->saveQuietly();
            $invitee->refresh();
        }
    }

    protected function recordCommunicationLog(
        Invitee $invitee,
        string $type,
        string $channel,
        string $recipient,
        string $message,
        string $status,
        ?string $providerMessageId = null,
        ?string $errorMessage = null,
        ?array $response = null,
        ?string $provider = null,
    ): void {
        $this->recordTableLog(
            table: 'message_logs',
            invitee: $invitee,
            type: $type,
            channel: $channel,
            recipient: $recipient,
            message: $message,
            status: $status,
            providerMessageId: $providerMessageId,
            errorMessage: $errorMessage,
            response: $response,
            provider: $provider,
        );

        $this->recordTableLog(
            table: 'sms_logs',
            invitee: $invitee,
            type: $type,
            channel: $channel,
            recipient: $recipient,
            message: $message,
            status: $status,
            providerMessageId: $providerMessageId,
            errorMessage: $errorMessage,
            response: $response,
            provider: $provider,
        );
    }

    protected function recordTableLog(
        string $table,
        Invitee $invitee,
        string $type,
        string $channel,
        string $recipient,
        string $message,
        string $status,
        ?string $providerMessageId = null,
        ?string $errorMessage = null,
        ?array $response = null,
        ?string $provider = null,
    ): void {
        if (! Schema::hasTable($table)) {
            return;
        }

        $now = now();
        $columns = Schema::getColumnListing($table);
        $encodedResponse = $response ? json_encode($response) : null;

        $row = [
            'event_id' => $invitee->event_id,
            'invitee_id' => $invitee->id,
            'type' => $type,
            'sms_type' => $type,
            'message_type' => $type,
            'channel' => $channel,
            'recipient' => $recipient,
            'phone' => $recipient,
            'to' => $recipient,
            'message' => $message,
            'body' => $message,
            'status' => $status,
            'provider' => $provider,
            'provider_message_id' => $providerMessageId,
            'message_id' => $providerMessageId,
            'error_message' => $errorMessage,
            'error' => $errorMessage,
            'send_source' => 'system',
            'sent_by' => Auth::id(),
            'user_id' => Auth::id(),
            'sent_at' => in_array($status, ['sent', 'logged'], true) ? $now : null,
            'failed_at' => $status === 'failed' ? $now : null,
            'provider_response' => $encodedResponse,
            'response' => $encodedResponse,
            'meta' => $encodedResponse,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $insertable = Arr::only($row, $columns);

        if (in_array('status', $columns, true) && blank($insertable['status'] ?? null)) {
            $insertable['status'] = $status;
        }

        DB::table($table)->insert($insertable);
    }

    public function buildInvitationMessage(Invitee $invitee): string
    {
        $invitee->loadMissing(['event', 'cardType']);

        $eventName = $this->eventName($invitee);
        $invitationLink = $this->privateInvitationLink($invitee);
        $serial = $invitee->serial_number ?: 'N/A';
        $guestCount = $invitee->final_allowed_guests ?? $invitee->allowed_guests ?? 1;

        return "Habari {$invitee->name},\n\n"
            . "Karibu kwenye {$eventName}. Fungua kadi yako ya mwaliko hapa:\n{$invitationLink}\n\n"
            . "Wageni: {$guestCount}\n"
            . "Serial No: {$serial}\n\n"
            . "eLive Card";
    }

    public function buildRsvpPendingReminderMessage(Invitee $invitee): string
    {
        $invitee->loadMissing(['event', 'cardType']);

        $eventName = $this->eventName($invitee);
        $date = $this->eventDate($invitee);
        $rsvpLink = $this->rsvpLink($invitee);
        $serial = $invitee->serial_number ?: 'N/A';

        return "Habari {$invitee->name},\n\n"
            . "Tafadhali thibitisha kuhudhuria {$eventName}.\n"
            . "Tarehe: {$date}\n"
            . "RSVP: {$rsvpLink}\n\n"
            . "Serial No: {$serial}\n\n"
            . "eLive Card";
    }

    public function buildAttendingReminderMessage(Invitee $invitee): string
    {
        $invitee->loadMissing(['event', 'cardType']);

        $eventName = $this->eventName($invitee);
        $venue = $this->eventVenue($invitee);
        $date = $this->eventDate($invitee);
        $time = $this->eventTime($invitee);
        $confirmedGuests = $invitee->confirmed_guests ?: ($invitee->final_allowed_guests ?? $invitee->allowed_guests ?? 1);
        $serial = $invitee->serial_number ?: 'N/A';

        return "Habari {$invitee->name},\n\n"
            . "Tunakukumbusha kuhusu {$eventName}.\n"
            . "Tarehe: {$date}\n"
            . "Muda: {$time}\n"
            . "Ukumbi: {$venue}\n"
            . "Wageni: {$confirmedGuests}\n"
            . "Serial No: {$serial}\n\n"
            . "eLive Card";
    }

    public function buildEventDayReminderMessage(Invitee $invitee): string
    {
        $invitee->loadMissing(['event', 'cardType']);

        $eventName = $this->eventName($invitee);
        $venue = $this->eventVenue($invitee);
        $time = $this->eventTime($invitee);
        $mapsLink = $invitee->event?->google_maps_link;
        $serial = $invitee->serial_number ?: 'N/A';

        $message = "Habari {$invitee->name},\n\n"
            . "Leo ni {$eventName}.\n"
            . "Muda: {$time}\n"
            . "Ukumbi: {$venue}\n"
            . "Serial No: {$serial}\n";

        if (filled($mapsLink)) {
            $message .= "Ramani: {$mapsLink}\n";
        }

        return $message . "\neLive Card";
    }

    public function replacePlaceholders(string $message, Invitee $invitee): string
    {
        $invitee->loadMissing(['event', 'cardType']);

        $replacements = [
            '#NAME#' => (string) $invitee->name,
            '{{name}}' => (string) $invitee->name,
            '#PHONE#' => (string) $invitee->phone,
            '{{phone}}' => (string) $invitee->phone,
            '#EVENT_NAME#' => $this->eventName($invitee),
            '{{event_name}}' => $this->eventName($invitee),
            '#INVITATION_LINK#' => $this->privateInvitationLink($invitee),
            '{{invitation_link}}' => $this->privateInvitationLink($invitee),
            '#CARD_LINK#' => $this->privateInvitationLink($invitee),
            '{{card_link}}' => $this->privateInvitationLink($invitee),
            '#RSVP_LINK#' => $this->rsvpLink($invitee),
            '{{rsvp_link}}' => $this->rsvpLink($invitee),
            '#SERIAL_NUMBER#' => (string) $invitee->serial_number,
            '{{serial_number}}' => (string) $invitee->serial_number,
            '#CARD_TYPE#' => (string) ($invitee->cardType?->name ?? $invitee->card_type ?? ''),
            '{{card_type}}' => (string) ($invitee->cardType?->name ?? $invitee->card_type ?? ''),
            '#GUEST_COUNT#' => (string) ($invitee->final_allowed_guests ?? $invitee->allowed_guests ?? 1),
            '{{guest_count}}' => (string) ($invitee->final_allowed_guests ?? $invitee->allowed_guests ?? 1),
            '#TABLE_NUMBER#' => (string) ($invitee->table_number ?? ''),
            '{{table_number}}' => (string) ($invitee->table_number ?? ''),
            '#CATEGORY#' => (string) ($invitee->category ?? ''),
            '{{category}}' => (string) ($invitee->category ?? ''),
            '#EVENT_DATE#' => $this->eventDate($invitee),
            '{{event_date}}' => $this->eventDate($invitee),
            '#EVENT_TIME#' => $this->eventTime($invitee),
            '{{event_time}}' => $this->eventTime($invitee),
            '#EVENT_VENUE#' => $this->eventVenue($invitee),
            '{{event_venue}}' => $this->eventVenue($invitee),
            '#LOCATION_LINK#' => (string) ($invitee->event?->google_maps_link ?? ''),
            '{{location_link}}' => (string) ($invitee->event?->google_maps_link ?? ''),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $message);
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

    protected function privateInvitationLink(Invitee $invitee): string
    {
        if (filled($invitee->private_invitation_url)) {
            return $invitee->private_invitation_url;
        }

        if (filled($invitee->short_code)) {
            return route('invitee.page', $invitee->short_code);
        }

        if (filled($invitee->qr_token)) {
            return url('/i/' . $invitee->qr_token);
        }

        return url('/');
    }

    protected function rsvpLink(Invitee $invitee): string
    {
        if (method_exists($invitee, 'rsvpUrl')) {
            return $invitee->rsvpUrl();
        }

        if (filled($invitee->rsvp_url)) {
            return $invitee->rsvp_url;
        }

        if (filled($invitee->rsvp_token)) {
            return url('/rsvp/' . $invitee->rsvp_token);
        }

        return $this->privateInvitationLink($invitee);
    }

    public function formatPhone(?string $phone): string
    {
        $phone = preg_replace('/\D+/', '', (string) $phone);

        if (blank($phone)) {
            throw new \Exception('Invitee phone number is missing.');
        }

        if (str_starts_with($phone, '00255')) {
            $phone = '255' . substr($phone, 5);
        }

        if (str_starts_with($phone, '2550')) {
            $phone = '255' . substr($phone, 4);
        }

        if (str_starts_with($phone, '0') && strlen($phone) === 10) {
            $phone = '255' . substr($phone, 1);
        }

        if (strlen($phone) === 9 && preg_match('/^[67]/', $phone)) {
            $phone = '255' . $phone;
        }

        if (! preg_match('/^255[67]\d{8}$/', $phone)) {
            throw new \Exception('Invalid Tanzania phone number: ' . $phone);
        }

        return $phone;
    }
}
