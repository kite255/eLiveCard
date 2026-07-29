<?php

namespace App\Services;

use App\Models\GeneratedCard;
use App\Models\Invitee;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class WhatsAppApiCloudService
{
    public function sendInvitation(Invitee $invitee): array
    {
        $invitee->loadMissing([
            'event',
            'cardType',
        ]);

        $event = $invitee->event;

        if (! $event) {
            throw new RuntimeException(
                'The selected invitee is not attached to an event.'
            );
        }

        if (blank($invitee->phone)) {
            throw new RuntimeException(
                'The selected invitee does not have a phone number.'
            );
        }

        $templateName = trim(
            (string) config(
                'services.whatsapp.templates.invitation',
                'event_invitation_en'
            )
        );

        $languageCode = trim(
            (string) config(
                'services.whatsapp.template_language',
                'en'
            )
        );

        $imageUrl = $this->resolveInvitationImageUrl($invitee);

        if (blank($imageUrl)) {
            throw new RuntimeException(
                'The WhatsApp invitation template requires an image header, but no public invitation-card image was found.'
            );
        }

        $components = [
            [
                'type' => 'header',
                'parameters' => [
                    [
                        'type' => 'image',
                        'image' => [
                            'link' => $imageUrl,
                        ],
                    ],
                ],
            ],
            [
                'type' => 'body',
                'parameters' => [
                    $this->textParameter($invitee->name),
                    $this->textParameter($event->title),
                    $this->textParameter(
                        $invitee->cardType?->name
                            ?? $invitee->card_type
                            ?? 'Invitation'
                    ),
                    $this->textParameter(
                        $event->venue_name
                            ?? $event->venue
                            ?? 'Venue will be communicated'
                    ),
                    $this->textParameter(
                        $this->formatEventTime($event)
                    ),
                ],
            ],
        ];

        return $this->sendTemplate(
            phone: (string) $invitee->phone,
            templateName: $templateName,
            languageCode: $languageCode,
            components: $components,
            invitee: $invitee,
            messageType: 'invitation',
        );
    }

    public function sendText(
        string $phone,
        string $message,
        ?Invitee $invitee = null
    ): array {
        if (blank(trim($message))) {
            throw new RuntimeException(
                'The WhatsApp text message cannot be empty.'
            );
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalizePhone($phone),
            'type' => 'text',
            'text' => [
                'preview_url' => true,
                'body' => trim($message),
            ],
        ];

        return $this->send(
            payload: $payload,
            invitee: $invitee,
            messageType: 'text',
            templateName: null,
            messageBody: trim($message),
        );
    }

    public function sendTemplate(
        string $phone,
        string $templateName,
        string $languageCode = 'en',
        array $components = [],
        ?Invitee $invitee = null,
        string $messageType = 'template',
    ): array {
        $templateName = trim($templateName);
        $languageCode = trim($languageCode);

        if ($templateName === '') {
            throw new RuntimeException(
                'The WhatsApp template name is required.'
            );
        }

        if ($languageCode === '') {
            throw new RuntimeException(
                'The WhatsApp template language is required.'
            );
        }

        $template = [
            'name' => $templateName,
            'language' => [
                'code' => $languageCode,
            ],
        ];

        if ($components !== []) {
            $template['components'] = $components;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalizePhone($phone),
            'type' => 'template',
            'template' => $template,
        ];

        return $this->send(
            payload: $payload,
            invitee: $invitee,
            messageType: $messageType,
            templateName: $templateName,
            messageBody: null,
        );
    }

    protected function send(
        array $payload,
        ?Invitee $invitee,
        string $messageType,
        ?string $templateName,
        ?string $messageBody,
    ): array {
        $this->validateConfiguration();

        $recipient = (string) ($payload['to'] ?? '');

        if ($recipient === '') {
            throw new RuntimeException(
                'A valid WhatsApp recipient phone number is required.'
            );
        }

        $pendingLogId = $this->createMessageLog(
            invitee: $invitee,
            recipient: $recipient,
            messageType: $messageType,
            templateName: $templateName,
            messageBody: $messageBody,
            requestPayload: $payload,
        );

        try {
            $response = Http::withToken(
                (string) config('services.whatsapp.access_token')
            )
                ->acceptJson()
                ->asJson()
                ->connectTimeout(
                    (int) config('services.whatsapp.connect_timeout', 10)
                )
                ->timeout(
                    (int) config('services.whatsapp.timeout', 30)
                )
                ->retry(
                    times: (int) config(
                        'services.whatsapp.retry_times',
                        2
                    ),
                    sleepMilliseconds: (int) config(
                        'services.whatsapp.retry_delay',
                        500
                    ),
                    when: static function (
                        Throwable $exception,
                        $request
                    ): bool {
                        return true;
                    },
                    throw: false,
                )
                ->post($this->messagesEndpoint(), $payload);

            $responseData = $response->json();

            if (! is_array($responseData)) {
                $responseData = [
                    'raw_body' => $response->body(),
                ];
            }

            if (! $response->successful()) {
                $this->handleFailedResponse(
                    response: $response,
                    responseData: $responseData,
                    invitee: $invitee,
                    messageLogId: $pendingLogId,
                    recipient: $recipient,
                    messageType: $messageType,
                    templateName: $templateName,
                );
            }

            $providerMessageId = trim((string) data_get(
                $responseData,
                'messages.0.id',
                ''
            ));

            $this->markMessageSubmitted(
                messageLogId: $pendingLogId,
                providerMessageId: $providerMessageId,
                responseData: $responseData,
            );

            $this->updateInviteeAfterSubmission(
                invitee: $invitee,
                providerMessageId: $providerMessageId,
            );

            AuditLogService::record(
                action: 'whatsapp_message.submitted',
                subject: $invitee,
                eventId: $invitee?->event_id,
                description: 'A WhatsApp message was accepted for processing by Meta.',
                metadata: [
                    'recipient' => $recipient,
                    'message_type' => $messageType,
                    'template_name' => $templateName,
                    'provider_message_id' => $providerMessageId,
                ],
            );

            return $responseData;
        } catch (Throwable $exception) {
            $this->markMessageFailed(
                messageLogId: $pendingLogId,
                errorMessage: $exception->getMessage(),
                responseData: null,
            );

            $this->updateInviteeAfterFailure(
                invitee: $invitee,
                errorMessage: $exception->getMessage(),
            );

            Log::error('WhatsApp Cloud API request failed.', [
                'invitee_id' => $invitee?->id,
                'event_id' => $invitee?->event_id,
                'recipient' => $recipient,
                'message_type' => $messageType,
                'template_name' => $templateName,
                'error' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw $exception;
        }
    }

    protected function handleFailedResponse(
        Response $response,
        array $responseData,
        ?Invitee $invitee,
        ?int $messageLogId,
        string $recipient,
        string $messageType,
        ?string $templateName,
    ): never {
        $errorMessage = (string) (
            data_get($responseData, 'error.error_data.details')
            ?? data_get($responseData, 'error.message')
            ?? 'WhatsApp Cloud API rejected the message.'
        );

        $errorCode = data_get($responseData, 'error.code');

        $this->markMessageFailed(
            messageLogId: $messageLogId,
            errorMessage: $errorMessage,
            responseData: $responseData,
        );

        $this->updateInviteeAfterFailure(
            invitee: $invitee,
            errorMessage: $errorMessage,
        );

        Log::error('WhatsApp Cloud API rejected a message.', [
            'status_code' => $response->status(),
            'invitee_id' => $invitee?->id,
            'event_id' => $invitee?->event_id,
            'recipient' => $recipient,
            'message_type' => $messageType,
            'template_name' => $templateName,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'response' => $responseData,
        ]);

        throw new RuntimeException(
            filled($errorCode)
                ? "WhatsApp error {$errorCode}: {$errorMessage}"
                : $errorMessage
        );
    }

    protected function messagesEndpoint(): string
    {
        $version = trim(
            (string) config('services.whatsapp.api_version', 'v25.0')
        );

        $phoneNumberId = trim(
            (string) config('services.whatsapp.phone_number_id')
        );

        $baseUrl = rtrim(
            (string) config(
                'services.whatsapp.base_url',
                'https://graph.facebook.com'
            ),
            '/'
        );

        return "{$baseUrl}/{$version}/{$phoneNumberId}/messages";
    }

    protected function validateConfiguration(): void
    {
        if (! (bool) config('services.whatsapp.enabled', false)) {
            throw new RuntimeException(
                'WhatsApp sending is disabled. Set WHATSAPP_ENABLED=true.'
            );
        }

        $driver = trim(
            (string) config('services.whatsapp.driver', 'log')
        );

        if ($driver !== 'meta_cloud_api') {
            throw new RuntimeException(
                'WhatsApp Cloud API is not active. Set WHATSAPP_DRIVER=meta_cloud_api.'
            );
        }

        $missing = [];

        foreach ([
            'access_token',
            'phone_number_id',
        ] as $key) {
            if (blank(config("services.whatsapp.{$key}"))) {
                $missing[] = "services.whatsapp.{$key}";
            }
        }

        if ($missing !== []) {
            throw new RuntimeException(
                'WhatsApp configuration is incomplete: '
                .implode(', ', $missing)
            );
        }
    }

    protected function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone) ?: '';

        if ($phone === '') {
            throw new RuntimeException(
                'The WhatsApp phone number is empty or invalid.'
            );
        }

        if (str_starts_with($phone, '0')) {
            $phone = '255'.substr($phone, 1);
        } elseif (
            ! str_starts_with($phone, '255')
            && strlen($phone) === 9
        ) {
            $phone = '255'.$phone;
        }

        if (! preg_match('/^[1-9][0-9]{7,14}$/', $phone)) {
            throw new RuntimeException(
                'The WhatsApp phone number must be in international format.'
            );
        }

        return $phone;
    }

    protected function resolveInvitationImageUrl(
        Invitee $invitee
    ): ?string {
        $generatedCard = GeneratedCard::query()
            ->where('invitee_id', $invitee->id)
            ->whereNotNull('file_path')
            ->where(function ($query): void {
                $query
                    ->where('status', 'generated')
                    ->orWhereNull('status');
            })
            ->latest('generated_at')
            ->latest('id')
            ->first();

        $path = trim((string) ($generatedCard?->file_path ?? ''));

        if ($path !== '') {
            if (
                str_starts_with($path, 'https://')
                || str_starts_with($path, 'http://')
            ) {
                return $path;
            }

            $path = ltrim($path, '/');

            if (str_starts_with($path, 'storage/')) {
                return url('/'.$path);
            }

            return url(Storage::disk('public')->url($path));
        }

        $fallbackUrl = trim(
            (string) config(
                'services.whatsapp.default_invitation_image_url',
                ''
            )
        );

        return $fallbackUrl !== ''
            ? $fallbackUrl
            : null;
    }

    protected function formatEventTime(object $event): string
    {
        $value = $event->start_time
            ?? $event->event_time
            ?? null;

        if (blank($value)) {
            return 'Time will be communicated';
        }

        try {
            return Carbon::parse($value)->format('H:i');
        } catch (Throwable) {
            return (string) $value;
        }
    }

    protected function textParameter(
        string|int|float|null $value
    ): array {
        return [
            'type' => 'text',
            'text' => filled($value)
                ? trim((string) $value)
                : '-',
        ];
    }

    protected function createMessageLog(
        ?Invitee $invitee,
        string $recipient,
        string $messageType,
        ?string $templateName,
        ?string $messageBody,
        array $requestPayload,
    ): ?int {
        if (! Schema::hasTable('message_logs')) {
            return null;
        }

        $columns = Schema::getColumnListing('message_logs');
        $now = now();

        $row = [
            'event_id' => $invitee?->event_id,
            'invitee_id' => $invitee?->id,
            'channel' => 'whatsapp',
            'type' => $messageType,
            'message_type' => $messageType,
            'recipient' => $recipient,
            'phone' => $recipient,
            'to' => $recipient,
            'message' => $messageBody,
            'body' => $messageBody,
            'template_name' => $templateName,
            'status' => 'pending',
            'provider' => 'WhatsApp Cloud API',
            'provider_name' => 'WhatsApp Cloud API',
            'provider_status' => 'pending',
            'request_payload' => json_encode(
                $requestPayload,
                JSON_UNESCAPED_SLASHES
            ),
            'meta' => json_encode(
                [
                    'template_name' => $templateName,
                    'message_type' => $messageType,
                ],
                JSON_UNESCAPED_SLASHES
            ),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $insertable = Arr::only($row, $columns);

        if ($insertable === []) {
            return null;
        }

        return (int) DB::table('message_logs')
            ->insertGetId($insertable);
    }

    protected function markMessageSubmitted(
        ?int $messageLogId,
        string $providerMessageId,
        array $responseData,
    ): void {
        if (
            ! $messageLogId
            || ! Schema::hasTable('message_logs')
        ) {
            return;
        }

        $columns = Schema::getColumnListing('message_logs');

        $update = [
            'status' => 'submitted',
            'provider_status' => 'accepted',
            'provider_message_id' => $providerMessageId,
            'message_id' => $providerMessageId,
            'wamid' => $providerMessageId,
            'external_message_id' => $providerMessageId,
            'provider_response' => json_encode(
                $responseData,
                JSON_UNESCAPED_SLASHES
            ),
            'response' => json_encode(
                $responseData,
                JSON_UNESCAPED_SLASHES
            ),
            'sent_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('message_logs')
            ->where('id', $messageLogId)
            ->update(Arr::only($update, $columns));
    }

    protected function markMessageFailed(
        ?int $messageLogId,
        string $errorMessage,
        ?array $responseData,
    ): void {
        if (
            ! $messageLogId
            || ! Schema::hasTable('message_logs')
        ) {
            return;
        }

        $columns = Schema::getColumnListing('message_logs');

        $encodedResponse = $responseData !== null
            ? json_encode(
                $responseData,
                JSON_UNESCAPED_SLASHES
            )
            : null;

        $update = [
            'status' => 'failed',
            'provider_status' => 'failed',
            'error_message' => $errorMessage,
            'error' => $errorMessage,
            'provider_response' => $encodedResponse,
            'response' => $encodedResponse,
            'failed_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('message_logs')
            ->where('id', $messageLogId)
            ->update(Arr::only($update, $columns));
    }

    protected function updateInviteeAfterSubmission(
        ?Invitee $invitee,
        string $providerMessageId,
    ): void {
        if (! $invitee) {
            return;
        }

        $columns = Schema::getColumnListing(
            $invitee->getTable()
        );

        $updates = [
            'last_message_channel' => 'whatsapp',
            'last_message_status' => 'submitted',
            'message_status' => 'submitted',
            'whatsapp_status' => 'submitted',
            'whatsapp_message_id' => $providerMessageId,
            'whatsapp_error' => null,
        ];

        $invitee->forceFill(
            Arr::only($updates, $columns)
        )->saveQuietly();
    }

    protected function updateInviteeAfterFailure(
        ?Invitee $invitee,
        string $errorMessage,
    ): void {
        if (! $invitee) {
            return;
        }

        $columns = Schema::getColumnListing(
            $invitee->getTable()
        );

        $updates = [
            'last_message_channel' => 'whatsapp',
            'last_message_status' => 'failed',
            'message_status' => 'failed',
            'whatsapp_status' => 'failed',
            'whatsapp_error' => $errorMessage,
            'failed_at' => now(),
        ];

        $invitee->forceFill(
            Arr::only($updates, $columns)
        )->saveQuietly();
    }
}
