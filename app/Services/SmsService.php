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
                providerStatus: $response['provider_status'] ?? $status,
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
                providerStatus: 'failed',
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
        $apiKey = config('services.sms.api_key')
            ?: config('services.elive_sms.api_key')
            ?: env('SMS_API_KEY')
            ?: env('ELIVE_SMS_API_KEY');

        $apiSecret = config('services.sms.api_secret')
            ?: config('services.elive_sms.api_secret')
            ?: env('SMS_API_SECRET')
            ?: env('ELIVE_SMS_API_SECRET');
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
            'shoot_id' => data_get($data, 'data.shootId')
                ?? data_get($data, 'data.shoot_id')
                ?? data_get($data, 'shootId')
                ?? data_get($data, 'shoot_id'),
            'message_id' => data_get($data, 'data.shootId')
                ?? data_get($data, 'data.shoot_id')
                ?? data_get($data, 'shootId')
                ?? data_get($data, 'shoot_id')
                ?? data_get($data, 'data.messageId')
                ?? data_get($data, 'message_id')
                ?? data_get($data, 'id')
                ?? $reference,
            'valid_contacts' => data_get($data, 'data.validContacts'),
            'invalid_contacts' => data_get($data, 'data.invalidContacts'),
            'duplicated_contacts' => data_get($data, 'data.duplicatedContacts'),
            'message_size' => data_get($data, 'data.messageSize'),
            'provider_status' => 'sent',
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
     * Resolve SMS send endpoint from either SMS_API_URL or base URL.
     *
     * Supported base URL formats:
     * - https://message.elive.co.tz
     * - https://message.elive.co.tz/api/v1/vendor/message
     */
    protected function resolveSmsApiUrl(): ?string
    {
        $apiUrl = config('services.sms.api_url') ?: env('SMS_API_URL');

        if (filled($apiUrl)) {
            return rtrim((string) $apiUrl, '/');
        }

        return rtrim($this->smsMessageBaseUrl(), '/') . '/send';
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
            || filled(data_get($data, 'data.shoot_id'))
            || filled(data_get($data, 'shootId'))
            || filled(data_get($data, 'shoot_id'))
            || filled(data_get($data, 'data.messageId'));
    }

    protected function extractMessageId(array $response, string $reference): string
    {
        return (string) (
            $response['shoot_id']
            ?? $response['message_id']
            ?? data_get($response, 'response.data.shootId')
            ?? data_get($response, 'response.data.shoot_id')
            ?? data_get($response, 'response.shootId')
            ?? data_get($response, 'response.shoot_id')
            ?? data_get($response, 'response.data.messageId')
            ?? $reference
        );
    }

    protected function smsProvider(): string
    {
        return (string) config('services.sms.provider', env('SMS_PROVIDER', 'eLive SMS'));
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
        ?string $providerStatus = null,
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
            providerStatus: $providerStatus,
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
            providerStatus: $providerStatus,
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
        ?string $providerStatus = null,
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
            'provider_name' => $provider,
            'provider_status' => $providerStatus ?? $status,
            'provider_message_id' => $providerMessageId,
            'shoot_id' => $providerMessageId,
            'message_id' => $providerMessageId,
            'error_message' => $errorMessage,
            'error' => $errorMessage,
            'send_source' => 'system',
            'sent_by' => Auth::id(),
            'user_id' => Auth::id(),
            'sent_at' => in_array($status, ['sent', 'logged', 'delivered', 'read'], true) ? $now : null,
            'delivered_at' => $status === 'delivered' ? $now : null,
            'read_at' => $status === 'read' ? $now : null,
            'failed_at' => in_array($status, ['failed', 'undelivered', 'expired', 'rejected'], true) ? $now : null,
            'provider_request' => $encodedResponse,
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


    /**
     * Fetch the actual SMS delivery report from eLive SMS provider using the shootId.
     *
     * Important:
     * Some providers may accept SMS successfully, but their delivery-report endpoint
     * may be unavailable or wrongly documented. This method must not crash the UI/job.
     * It returns a structured response instead.
     */
    public function getDeliveryReport(string $shootId): array
    {
        $shootId = trim($shootId);

        if (blank($shootId)) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'SMS shootId is missing.',
                'shoot_id' => $shootId,
                'url' => null,
                'data' => null,
            ];
        }

        $apiKey = config('services.elive_sms.api_key')
            ?: config('services.sms.api_key')
            ?: env('ELIVE_SMS_API_KEY')
            ?: env('SMS_API_KEY');

        $apiSecret = config('services.elive_sms.api_secret')
            ?: config('services.sms.api_secret')
            ?: env('ELIVE_SMS_API_SECRET')
            ?: env('SMS_API_SECRET');

        if (blank($apiKey) || blank($apiSecret)) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'SMS API key or API secret is not configured.',
                'shoot_id' => $shootId,
                'url' => null,
                'data' => null,
            ];
        }

        $url = $this->deliveryReportUrl($shootId);

        try {
            $response = Http::timeout((int) config('services.elive_sms.timeout', config('services.sms.timeout', env('SMS_TIMEOUT', 30))))
                ->acceptJson()
                ->withHeaders($this->smsAuthHeaders((string) $apiKey, (string) $apiSecret))
                ->get($url);
        } catch (Throwable $exception) {
            Log::warning('eLive SMS delivery report request exception', [
                'shoot_id' => $shootId,
                'url' => $url,
                'error' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 0,
                'message' => $exception->getMessage(),
                'shoot_id' => $shootId,
                'url' => $url,
                'data' => null,
            ];
        }

        $data = $response->json();

        Log::info('eLive SMS delivery report response', [
            'shoot_id' => $shootId,
            'url' => $url,
            'http_status' => $response->status(),
            'response' => $data,
            'body' => $response->failed() ? Str::limit($response->body(), 500) : null,
        ]);

        if (! $response->successful()) {
            return [
                'success' => false,
                'status' => $response->status(),
                'message' => $data['message'] ?? Str::limit($response->body(), 500),
                'shoot_id' => $shootId,
                'url' => $url,
                'data' => $data,
            ];
        }

        return [
            'success' => true,
            'status' => $response->status(),
            'message' => $data['message'] ?? 'SMS delivery report fetched.',
            'shoot_id' => $shootId,
            'url' => $url,
            'data' => $data,
        ];
    }

    /**
     * Fetch delivery report by shootId and update sms_logs/message_logs with actual provider status.
     *
     * If the provider delivery endpoint fails, the SMS must remain "sent" because
     * the original send API already accepted/submitted the message.
     */
    public function refreshDeliveryReport(string $shootId): array
    {
        $reportResponse = $this->getDeliveryReport($shootId);

        if (! ($reportResponse['success'] ?? false)) {
            $this->markDeliveryReportUnavailable(
                shootId: $shootId,
                message: $reportResponse['message'] ?? 'Delivery report unavailable.',
                response: $reportResponse,
            );

            return $reportResponse;
        }

        $data = $reportResponse['data'] ?? [];

        $reports = $data['data'] ?? [];

        if (is_array($reports) && Arr::isAssoc($reports)) {
            $reports = [$reports];
        }

        if (! is_array($reports) || $reports === []) {
            $providerStatus = $data['status']
                ?? $data['provider_status']
                ?? $data['messageStatus']
                ?? $data['message_status']
                ?? null;

            if (filled($providerStatus)) {
                $reports = [[
                    'status' => $providerStatus,
                    'message' => $data['message'] ?? null,
                ]];
            }
        }

        foreach ($reports as $report) {
            $this->updateDeliveryReportLogs($shootId, is_array($report) ? $report : []);
        }

        return $reportResponse;
    }

    /**
     * Check the current SMS balance from eLive SMS provider.
     */
    public function getBalance(): ?int
    {
        $apiKey = config('services.sms.api_key')
            ?: config('services.elive_sms.api_key')
            ?: env('SMS_API_KEY')
            ?: env('ELIVE_SMS_API_KEY');

        $apiSecret = config('services.sms.api_secret')
            ?: config('services.elive_sms.api_secret')
            ?: env('SMS_API_SECRET')
            ?: env('ELIVE_SMS_API_SECRET');

        if (blank($apiKey) || blank($apiSecret)) {
            return null;
        }

        $response = Http::timeout((int) config('services.sms.timeout', env('SMS_TIMEOUT', 30)))
            ->acceptJson()
            ->withHeaders($this->smsAuthHeaders((string) $apiKey, (string) $apiSecret))
            ->get($this->balanceUrl());

        if ($response->failed()) {
            Log::warning('Failed to fetch eLive SMS balance', [
                'http_status' => $response->status(),
                'body' => Str::limit($response->body(), 500),
            ]);

            return null;
        }

        return (int) (
            $response->json('data.totalSms')
            ?? $response->json('data.total_sms')
            ?? $response->json('totalSms')
            ?? $response->json('total_sms')
            ?? 0
        );
    }

    /**
     * Keep SMS as sent when the provider delivery endpoint is unavailable.
     *
     * Important:
     * - Do NOT mark the SMS as failed because the original send API accepted it.
     * - Do NOT overwrite provider_response because it contains the original send response/shootId.
     * - Store the delivery-report error only in error/provider_status fields.
     */
    protected function markDeliveryReportUnavailable(string $shootId, string $message, array $response = []): void
    {
        $now = now();
        $encodedResponse = json_encode($response);

        foreach (['message_logs', 'sms_logs'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $columns = Schema::getColumnListing($table);

            $update = [
                'provider_status' => 'delivery_report_unavailable',
                'delivery_status' => 'delivery_report_unavailable',
                'delivery_report_status' => 'unavailable',
                'delivery_report_error' => $message,
                'delivery_report_response' => $encodedResponse,
                'delivery_report_checked_at' => $now,
                'error_message' => $message,
                'error' => $message,
                'updated_at' => $now,
            ];

            /*
             * Keep the display status honest:
             * - If the SMS is still sent/queued/sending/unknown, keep or set it to sent.
             * - Never mark it as failed because only the report lookup failed.
             */
            if (in_array('status', $columns, true)) {
                $update['status'] = DB::raw("CASE WHEN status IN ('delivered', 'failed', 'undelivered', 'expired', 'rejected') THEN status ELSE 'sent' END");
            }

            $safeUpdate = Arr::only($update, $columns);

            if ($safeUpdate === []) {
                continue;
            }

            DB::table($table)
                ->where(function ($query) use ($columns, $shootId) {
                    if (in_array('provider_message_id', $columns, true)) {
                        $query->orWhere('provider_message_id', $shootId);
                    }

                    if (in_array('message_id', $columns, true)) {
                        $query->orWhere('message_id', $shootId);
                    }

                    if (in_array('shoot_id', $columns, true)) {
                        $query->orWhere('shoot_id', $shootId);
                    }
                })
                ->update($safeUpdate);
        }
    }

    protected function updateDeliveryReportLogs(string $shootId, array $report): void
    {
        /*
         * Example eLive provider report:
         * status: Operator Submitted
         * statusCode: ACK
         *
         * This means the SMS was accepted/submitted by the operator.
         * It is NOT a handset delivery confirmation, so our internal status stays "sent".
         */
        $providerStatus = trim((string) (
            $report['status']
            ?? $report['provider_status']
            ?? $report['messageStatus']
            ?? $report['message_status']
            ?? 'unknown'
        ));

        $providerStatusCode = trim((string) (
            $report['statusCode']
            ?? $report['status_code']
            ?? $report['code']
            ?? ''
        ));

        $status = $this->normalizeProviderDeliveryStatus($providerStatus, $providerStatusCode);
        $now = now();
        $reportTime = $this->providerReportTime($report) ?? $now;

        $update = [
            'status' => $status,
            'provider_status' => $providerStatus,
            'provider_response' => json_encode($report),
            'response' => json_encode($report),
            'delivery_report_checked_at' => $now,
            'updated_at' => $now,
        ];

        if ($status === 'sent') {
            $update['sent_at'] = DB::raw('COALESCE(sent_at, NOW())');
            $update['delivered_at'] = null;
            $update['failed_at'] = null;
            $update['error_message'] = null;
            $update['error'] = null;
        }

        if ($status === 'delivered') {
            $update['delivered_at'] = $reportTime;
            $update['failed_at'] = null;
            $update['error_message'] = null;
            $update['error'] = null;
        }

        if ($status === 'read') {
            $update['read_at'] = $reportTime;
            $update['failed_at'] = null;
            $update['error_message'] = null;
            $update['error'] = null;
        }

        if (in_array($status, ['failed', 'undelivered', 'expired', 'rejected'], true)) {
            $update['failed_at'] = $reportTime;
            $update['error_message'] = $report['explanation']
                ?? $report['message']
                ?? 'SMS was not delivered.';
            $update['error'] = $update['error_message'];
        }

        if ($status === 'unknown') {
            /*
             * The provider returned a report, so remove old route errors.
             * Keep provider_status visible for manual/provider review.
             */
            $update['error_message'] = null;
            $update['error'] = null;
            $update['failed_at'] = null;
        }

        foreach (['message_logs', 'sms_logs'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $columns = Schema::getColumnListing($table);
            $safeUpdate = Arr::only($update, $columns);

            if ($safeUpdate === []) {
                continue;
            }

            $query = DB::table($table)
                ->where(function ($query) use ($columns, $shootId) {
                    if (in_array('provider_message_id', $columns, true)) {
                        $query->orWhere('provider_message_id', $shootId);
                    }

                    if (in_array('message_id', $columns, true)) {
                        $query->orWhere('message_id', $shootId);
                    }

                    if (in_array('shoot_id', $columns, true)) {
                        $query->orWhere('shoot_id', $shootId);
                    }
                });

            $mobile = $report['mobile'] ?? $report['phone'] ?? $report['recipient'] ?? null;

            if (filled($mobile)) {
                $query->where(function ($query) use ($columns, $mobile) {
                    if (in_array('phone', $columns, true)) {
                        $query->orWhere('phone', $mobile);
                    }

                    if (in_array('recipient', $columns, true)) {
                        $query->orWhere('recipient', $mobile);
                    }

                    if (in_array('to', $columns, true)) {
                        $query->orWhere('to', $mobile);
                    }
                });
            }

            $query->update($safeUpdate);
        }
    }

    protected function normalizeProviderDeliveryStatus(?string $providerStatus, ?string $providerStatusCode = null): string
    {
        $status = Str::of((string) $providerStatus)
            ->lower()
            ->trim()
            ->replace(['-', ' '], '_')
            ->toString();

        $statusCode = Str::of((string) $providerStatusCode)
            ->lower()
            ->trim()
            ->replace(['-', ' '], '_')
            ->toString();

        return match (true) {
            in_array($status, [
                'delivered',
                'delivery_success',
                'success',
                'delivrd',
            ], true) => 'delivered',

            in_array($status, [
                'operator_submitted',
                'operator_submit',
                'submitted',
                'sent',
                'senderid',
                'accepted',
                'message_submit',
                'submitted_successfully',
                'submit_sm',
            ], true),
            in_array($statusCode, [
                'ack',
                'sent',
                'submitted',
                'accepted',
                'submit_sm',
            ], true) => 'sent',

            in_array($status, [
                'delivery_report_unavailable',
                'report_unavailable',
            ], true) => 'sent',

            in_array($status, [
                'queued',
                'queue',
            ], true) => 'queued',

            in_array($status, [
                'sending',
                'processing',
            ], true) => 'sending',

            in_array($status, [
                'failed',
                'failure',
                'nack',
            ], true),
            in_array($statusCode, [
                'nack',
                'failed',
            ], true) => 'failed',

            in_array($status, [
                'undelivered',
                'undeliv',
                'not_delivered',
            ], true) => 'undelivered',

            in_array($status, [
                'expired',
                'expd',
            ], true) => 'expired',

            in_array($status, [
                'rejected',
                'reject',
                'rejectd',
            ], true) => 'rejected',

            $status === 'read' => 'read',

            default => 'unknown',
        };
    }

    protected function providerReportTime(array $report): ?Carbon
    {
        $time = $report['deliveryTime']
            ?? $report['sentAt']
            ?? $report['deliveredAt']
            ?? $report['deliveryAt']
            ?? $report['updatedAt']
            ?? null;

        if (blank($time)) {
            return null;
        }

        foreach (['d-m-Y H:i:s', 'd-m-Y H:i', 'Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
            try {
                return Carbon::createFromFormat($format, (string) $time);
            } catch (Throwable) {
                // Try next format.
            }
        }

        try {
            return Carbon::parse($time);
        } catch (Throwable) {
            return null;
        }
    }

    protected function deliveryReportUrl(string $shootId): string
    {
        /*
         * Correct eLive provider endpoint:
         * GET /api/v1/vendor/message/deliver/{shootId}
         *
         * Keep it configurable in .env:
         * ELIVE_SMS_DELIVERY_REPORT_PATH=/deliver/{shootId}
         */
        $path = (string) (
            config('services.elive_sms.delivery_report_path')
            ?: env('ELIVE_SMS_DELIVERY_REPORT_PATH', '/deliver/{shootId}')
        );

        $path = '/' . ltrim($path, '/');
        $path = str_replace('{shootId}', rawurlencode($shootId), $path);

        if (! str_contains($path, rawurlencode($shootId))) {
            $path = rtrim($path, '/') . '/' . rawurlencode($shootId);
        }

        return rtrim($this->smsMessageBaseUrl(), '/') . $path;
    }

    protected function balanceUrl(): string
    {
        return rtrim($this->smsMessageBaseUrl(), '/') . '/balance';
    }

    /**
     * Returns the provider message API base URL ending with /api/v1/vendor/message.
     */
    protected function smsMessageBaseUrl(): string
    {
        $baseUrl = config('services.sms.base_url')
            ?: config('services.elive_sms.base_url')
            ?: env('SMS_BASE_URL')
            ?: env('ELIVE_SMS_BASE_URL')
            ?: 'https://message.elive.co.tz';

        $baseUrl = rtrim((string) $baseUrl, '/');

        return Str::endsWith($baseUrl, '/api/v1/vendor/message')
            ? $baseUrl
            : $baseUrl . '/api/v1/vendor/message';
    }

    protected function smsBaseUrl(): string
    {
        return Str::before($this->smsMessageBaseUrl(), '/api/v1/vendor/message');
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
