<?php

namespace App\Http\Controllers;

use App\Models\Invitee;
use App\Services\AuditLogService;
use App\Services\RsvpService;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response|JsonResponse
    {
        $mode = $request->query('hub_mode');
        $verifyToken = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $configuredToken = (string) config(
            'services.whatsapp.webhook_verify_token'
        );

        if (
            $mode === 'subscribe'
            && $configuredToken !== ''
            && hash_equals($configuredToken, (string) $verifyToken)
        ) {
            Log::info('WhatsApp webhook verified successfully.');

            AuditLogService::system(
                action: 'whatsapp_webhook.verification_succeeded',
                description: 'WhatsApp webhook verification succeeded.',
                metadata: [
                    'mode' => $mode,
                    'challenge_present' => filled($challenge),
                ],
            );

            return response(
                (string) $challenge,
                SymfonyResponse::HTTP_OK,
                ['Content-Type' => 'text/plain']
            );
        }

        Log::warning('WhatsApp webhook verification failed.', [
            'mode' => $mode,
            'token_present' => filled($verifyToken),
            'challenge_present' => filled($challenge),
        ]);

        AuditLogService::system(
            action: 'whatsapp_webhook.verification_failed',
            description: 'WhatsApp webhook verification failed.',
            metadata: [
                'mode' => $mode,
                'token_present' => filled($verifyToken),
                'challenge_present' => filled($challenge),
            ],
        );

        return response()->json([
            'message' => 'WhatsApp webhook verification failed.',
        ], SymfonyResponse::HTTP_FORBIDDEN);
    }

    public function handle(
        Request $request,
        RsvpService $rsvpService,
        WhatsAppService $whatsAppService
    ): JsonResponse
    {
        if (! $this->hasValidSignature($request)) {
            Log::warning('WhatsApp webhook rejected because of invalid signature.');

            AuditLogService::system(
                action: 'whatsapp_webhook.invalid_signature',
                description: 'WhatsApp webhook request was rejected because its signature was invalid.',
                metadata: [
                    'signature_present' => filled(
                        $request->header('X-Hub-Signature-256')
                    ),
                ],
            );

            return response()->json([
                'message' => 'Invalid webhook signature.',
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }

        $payload = $request->json()->all();

        Log::info('WhatsApp webhook received.', [
            'object' => $payload['object'] ?? null,
            'entry_count' => count($payload['entry'] ?? []),
        ]);

        AuditLogService::system(
            action: 'whatsapp_webhook.received',
            description: 'WhatsApp webhook payload was received.',
            metadata: [
                'object' => $payload['object'] ?? null,
                'entry_count' => count($payload['entry'] ?? []),
            ],
        );

        $messagesProcessed = 0;
        $statusesProcessed = 0;
        $errors = 0;

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                foreach ($value['messages'] ?? [] as $message) {
                    try {
                        $this->handleIncomingMessage(
                            $message,
                            $rsvpService,
                            $whatsAppService
                        );
                        $messagesProcessed++;
                    } catch (Throwable $exception) {
                        $errors++;

                        Log::error('WhatsApp incoming message processing failed.', [
                            'message_id' => $message['id'] ?? null,
                            'error' => $exception->getMessage(),
                        ]);

                        AuditLogService::system(
                            action: 'whatsapp_webhook.message_processing_failed',
                            description: 'WhatsApp incoming message processing failed.',
                            metadata: [
                                'message_id' => $message['id'] ?? null,
                                'message_type' => $message['type'] ?? null,
                                'error' => $exception->getMessage(),
                                'exception' => $exception::class,
                            ],
                        );
                    }
                }

                foreach ($value['statuses'] ?? [] as $status) {
                    try {
                        $this->handleMessageStatus($status);
                        $statusesProcessed++;
                    } catch (Throwable $exception) {
                        $errors++;

                        Log::error('WhatsApp status processing failed.', [
                            'message_id' => $status['id'] ?? null,
                            'status' => $status['status'] ?? null,
                            'error' => $exception->getMessage(),
                        ]);

                        AuditLogService::system(
                            action: 'whatsapp_webhook.status_processing_failed',
                            description: 'WhatsApp delivery-status processing failed.',
                            metadata: [
                                'message_id' => $status['id'] ?? null,
                                'status' => $status['status'] ?? null,
                                'error' => $exception->getMessage(),
                                'exception' => $exception::class,
                            ],
                        );
                    }
                }
            }
        }

        AuditLogService::system(
            action: 'whatsapp_webhook.processed',
            description: 'WhatsApp webhook payload processing completed.',
            metadata: [
                'messages_processed' => $messagesProcessed,
                'statuses_processed' => $statusesProcessed,
                'errors' => $errors,
            ],
        );

        return response()->json([
            'received' => true,
        ], SymfonyResponse::HTTP_OK);
    }

    protected function handleIncomingMessage(
        array $message,
        RsvpService $rsvpService,
        WhatsAppService $whatsAppService
    ): void {
        $fromPhone = $this->normalizePhone(
            (string) ($message['from'] ?? '')
        );

        $messageId = trim((string) ($message['id'] ?? ''));
        $messageType = (string) ($message['type'] ?? 'unknown');

        [$replyPayload, $replyTitle] = $this->extractIncomingReply($message);

        if ($fromPhone === '') {
            Log::warning(
                'WhatsApp incoming message ignored because phone number is missing.',
                [
                    'message_id' => $messageId,
                    'type' => $messageType,
                ]
            );

            AuditLogService::system(
                action: 'whatsapp_message.phone_missing',
                description: 'Incoming WhatsApp message was ignored because the sender phone number was missing.',
                metadata: [
                    'message_id' => $messageId,
                    'message_type' => $messageType,
                ],
            );

            return;
        }

        $invitee = $this->findInviteeByPhone($fromPhone);

        if (! $invitee) {
            Log::warning(
                'WhatsApp reply ignored because invitee was not found.',
                [
                    'from' => $fromPhone,
                    'reply_payload' => $replyPayload,
                    'reply_title' => $replyTitle,
                    'message_id' => $messageId,
                ]
            );

            AuditLogService::system(
                action: 'whatsapp_message.invitee_not_found',
                description: 'WhatsApp reply was ignored because no invitee matched the sender phone number.',
                metadata: [
                    'from' => $fromPhone,
                    'message_id' => $messageId,
                    'message_type' => $messageType,
                    'reply_payload' => $replyPayload,
                    'reply_title' => $replyTitle,
                ],
            );

            return;
        }

        if ($this->incomingMessageAlreadyProcessed($messageId)) {
            Log::info('Duplicate WhatsApp message ignored.', [
                'message_id' => $messageId,
                'invitee_id' => $invitee->id,
            ]);

            AuditLogService::record(
                action: 'whatsapp_message.duplicate_ignored',
                subject: $invitee,
                eventId: $invitee->event_id,
                description: 'Duplicate WhatsApp reply was ignored.',
                metadata: [
                    'message_id' => $messageId,
                    'reply_payload' => $replyPayload,
                    'reply_title' => $replyTitle,
                ],
            );

            return;
        }

        if ($replyPayload === null && $replyTitle === null) {
            AuditLogService::record(
                action: 'whatsapp_message.received',
                subject: $invitee,
                eventId: $invitee->event_id,
                description: 'A WhatsApp message was received without a supported reply value.',
                metadata: [
                    'message_id' => $messageId,
                    'message_type' => $messageType,
                    'from' => $fromPhone,
                ],
            );

            return;
        }

        $canonicalAction = $this->resolveReplyAction(
            payload: $replyPayload,
            title: $replyTitle,
        );

        if ($canonicalAction === 'location') {
            $this->handleLocationReply(
                invitee: $invitee,
                whatsappService: $whatsAppService,
                messageId: $messageId,
                fromPhone: $fromPhone,
                messageType: $messageType,
                replyPayload: $replyPayload,
                replyTitle: $replyTitle,
            );

            return;
        }

        if (! in_array(
            $canonicalAction,
            ['rsvp_attending', 'rsvp_not_attending'],
            true
        )) {
            $this->recordIncomingMessage(
                invitee: $invitee,
                messageId: $messageId,
                fromPhone: $fromPhone,
                messageType: $messageType,
                buttonPayload: $replyPayload ?? $replyTitle ?? 'unknown',
                buttonTitle: $replyTitle,
                logType: 'message_reply',
            );

            AuditLogService::record(
                action: 'whatsapp_message.unsupported_reply',
                subject: $invitee,
                eventId: $invitee->event_id,
                description: 'A WhatsApp reply was received but it did not match a supported action.',
                metadata: [
                    'message_id' => $messageId,
                    'reply_payload' => $replyPayload,
                    'reply_title' => $replyTitle,
                ],
            );

            return;
        }

        $beforeValues = $invitee->only([
            'rsvp_status',
            'rsvp_confirmed_at',
            'last_message_channel',
            'last_message_status',
            'last_reply_message',
            'last_reply_at',
        ]);

        $updatedInvitee = $rsvpService->updateFromWhatsappButton(
            invitee: $invitee,
            buttonPayload: $canonicalAction,
            buttonTitle: $replyTitle,
        );

        $this->recordIncomingMessage(
            invitee: $updatedInvitee,
            messageId: $messageId,
            fromPhone: $fromPhone,
            messageType: $messageType,
            buttonPayload: $canonicalAction,
            buttonTitle: $replyTitle,
            logType: 'rsvp_reply',
        );

        AuditLogService::updated(
            subject: $updatedInvitee,
            eventId: $updatedInvitee->event_id,
            description: 'Invitee RSVP was updated from a WhatsApp response.',
            oldValues: $beforeValues,
            newValues: $updatedInvitee->only([
                'rsvp_status',
                'rsvp_confirmed_at',
                'last_message_channel',
                'last_message_status',
                'last_reply_message',
                'last_reply_at',
            ]),
            metadata: [
                'message_id' => $messageId,
                'phone' => $fromPhone,
                'reply_payload' => $replyPayload,
                'reply_title' => $replyTitle,
                'canonical_action' => $canonicalAction,
                'source' => 'whatsapp_webhook',
            ],
        );

        Log::info('WhatsApp RSVP reply processed.', [
            'invitee_id' => $updatedInvitee->id,
            'event_id' => $updatedInvitee->event_id,
            'phone' => $fromPhone,
            'canonical_action' => $canonicalAction,
            'before_status' => $beforeValues['rsvp_status'] ?? null,
            'after_status' => $updatedInvitee->rsvp_status,
        ]);
    }

    protected function extractIncomingReply(array $message): array
    {
        $payload = data_get(
            $message,
            'interactive.button_reply.id'
        );

        $title = data_get(
            $message,
            'interactive.button_reply.title'
        );

        if (! filled($payload) && ! filled($title)) {
            $payload = data_get(
                $message,
                'interactive.list_reply.id'
            );

            $title = data_get(
                $message,
                'interactive.list_reply.title'
            );
        }

        if (! filled($payload) && ! filled($title)) {
            $payload = data_get($message, 'button.payload');
            $title = data_get($message, 'button.text');
        }

        if (! filled($payload) && ! filled($title)) {
            $text = data_get($message, 'text.body');

            if (filled($text)) {
                $payload = $text;
                $title = $text;
            }
        }

        return [
            filled($payload) ? trim((string) $payload) : null,
            filled($title) ? trim((string) $title) : null,
        ];
    }

    protected function resolveReplyAction(
        ?string $payload,
        ?string $title
    ): ?string {
        $value = $this->normalizeReplyValue(
            $payload ?: $title ?: ''
        );

        return match ($value) {
            'rsvp_attending',
            'attending',
            'yes',
            'ndiyo',
            'nitahudhuria',
            'nitahuduria',
            'nita hudhuria',
            'nita huduria' => 'rsvp_attending',

            'rsvp_not_attending',
            'not_attending',
            'not attending',
            'no',
            'hapana',
            'sitaweza hudhuria',
            'sitaweza kuhudhuria',
            'sitaweza huduria',
            'sita hudhuria',
            'sita kuhudhuria' => 'rsvp_not_attending',

            'location',
            'view location',
            'open location',
            'fungua location',
            'angalia mahali',
            'mahali',
            'ramani' => 'location',

            default => null,
        };
    }

    protected function normalizeReplyValue(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['-', '_'], ' ', $value);

        return preg_replace('/\s+/', ' ', $value) ?: '';
    }

    protected function handleLocationReply(
        Invitee $invitee,
        WhatsAppService $whatsappService,
        string $messageId,
        string $fromPhone,
        string $messageType,
        ?string $replyPayload,
        ?string $replyTitle,
    ): void {
        $invitee->loadMissing('event');

        $event = $invitee->event;
        $locationUrl = trim((string) ($event?->google_maps_link ?? ''));

        $responseMessage = $locationUrl !== ''
            ? "Mahali pa tukio la {$event->title}:\n{$locationUrl}"
            : 'Samahani, kiungo cha Google Maps cha tukio hili bado hakijawekwa.';

        $whatsappService->sendText(
            phone: $invitee->phone,
            message: $responseMessage,
        );

        $invitee->forceFill([
            'last_message_channel' => 'whatsapp',
            'last_message_status' => 'replied',
            'last_reply_message' => $replyTitle ?: $replyPayload,
            'last_reply_at' => now(),
        ])->save();

        $this->recordIncomingMessage(
            invitee: $invitee,
            messageId: $messageId,
            fromPhone: $fromPhone,
            messageType: $messageType,
            buttonPayload: $replyPayload ?? 'location',
            buttonTitle: $replyTitle,
            logType: 'location_request',
        );

        AuditLogService::record(
            action: 'whatsapp_location.sent',
            subject: $invitee,
            eventId: $invitee->event_id,
            description: $locationUrl !== ''
                ? 'The event location was sent after a WhatsApp request.'
                : 'A WhatsApp location request was received, but the event had no Google Maps link.',
            metadata: [
                'message_id' => $messageId,
                'from' => $fromPhone,
                'location_available' => $locationUrl !== '',
            ],
        );
    }

    protected function handleMessageStatus(array $status): void
    {
        $messageId = trim((string) ($status['id'] ?? ''));
        $recipient = $this->normalizePhone(
            (string) ($status['recipient_id'] ?? '')
        );

        $providerStatus = strtolower(
            trim((string) ($status['status'] ?? 'unknown'))
        );

        $timestamp = $this->parseWhatsappTimestamp(
            $status['timestamp'] ?? null
        );

        $error = $this->extractWhatsappError($status);

        Log::info('WhatsApp message status received.', [
            'message_id' => $messageId,
            'recipient_id' => $recipient,
            'status' => $providerStatus,
            'timestamp' => $status['timestamp'] ?? null,
        ]);

        if ($messageId === '') {
            AuditLogService::system(
                action: 'whatsapp_status.message_id_missing',
                description: 'WhatsApp status update was ignored because the provider message ID was missing.',
                metadata: [
                    'recipient_id' => $recipient,
                    'status' => $providerStatus,
                ],
            );

            return;
        }

        $matchedLog = $this->findWhatsappMessageLog($messageId);

        if (! $matchedLog) {
            AuditLogService::system(
                action: 'whatsapp_status.message_not_found',
                description: 'WhatsApp status update could not be matched to a message log.',
                metadata: [
                    'message_id' => $messageId,
                    'recipient_id' => $recipient,
                    'status' => $providerStatus,
                    'error' => $error,
                ],
            );

            return;
        }

        $normalizedStatus = $this->normalizeWhatsappStatus(
            $providerStatus
        );

        $oldValues = Arr::only((array) $matchedLog, [
            'status',
            'provider_status',
            'sent_at',
            'delivered_at',
            'read_at',
            'failed_at',
            'error_message',
        ]);

        $this->updateWhatsappMessageLogs(
            messageId: $messageId,
            normalizedStatus: $normalizedStatus,
            providerStatus: $providerStatus,
            timestamp: $timestamp,
            error: $error,
            rawStatus: $status,
        );

        $invitee = ! empty($matchedLog->invitee_id)
            ? Invitee::find($matchedLog->invitee_id)
            : null;

        $newValues = [
            'status' => $normalizedStatus,
            'provider_status' => $providerStatus,
            'sent_at' => in_array(
                $normalizedStatus,
                ['sent', 'delivered', 'read'],
                true
            ) ? $timestamp : ($matchedLog->sent_at ?? null),
            'delivered_at' => in_array(
                $normalizedStatus,
                ['delivered', 'read'],
                true
            ) ? $timestamp : null,
            'read_at' => $normalizedStatus === 'read'
                ? $timestamp
                : null,
            'failed_at' => $normalizedStatus === 'failed'
                ? $timestamp
                : null,
            'error_message' => $error,
        ];

        if ($invitee) {
            $this->updateInviteeWhatsappStatus(
                invitee: $invitee,
                status: $normalizedStatus,
                messageId: $messageId,
                error: $error,
                timestamp: $timestamp,
            );

            AuditLogService::record(
                action: 'whatsapp_status.updated',
                subject: $invitee,
                eventId: $invitee->event_id,
                description: 'WhatsApp delivery status was updated.',
                oldValues: $oldValues,
                newValues: $newValues,
                metadata: [
                    'message_id' => $messageId,
                    'recipient_id' => $recipient,
                    'provider_status' => $providerStatus,
                    'normalized_status' => $normalizedStatus,
                    'error' => $error,
                    'source' => 'whatsapp_webhook',
                ],
            );
        } else {
            AuditLogService::system(
                action: 'whatsapp_status.updated',
                description: 'WhatsApp delivery status was updated, but no invitee was linked to the message log.',
                eventId: $matchedLog->event_id ?? null,
                metadata: [
                    'message_id' => $messageId,
                    'recipient_id' => $recipient,
                    'provider_status' => $providerStatus,
                    'normalized_status' => $normalizedStatus,
                    'error' => $error,
                ],
            );
        }
    }

    protected function findInviteeByPhone(string $phone): ?Invitee
    {
        $normalizedPhone = $this->normalizePhone($phone);

        if (! $normalizedPhone) {
            return null;
        }

        return Invitee::query()
            ->where(function ($query) use ($normalizedPhone) {
                $query
                    ->where('phone', $normalizedPhone)
                    ->orWhere('phone', '+'.$normalizedPhone)
                    ->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', '') = ?",
                        [$normalizedPhone]
                    );
            })
            ->latest('id')
            ->first();
    }

    protected function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone) ?: '';

        if ($phone === '') {
            return '';
        }

        if (str_starts_with($phone, '0')) {
            return '255'.substr($phone, 1);
        }

        if (! str_starts_with($phone, '255') && strlen($phone) === 9) {
            return '255'.$phone;
        }

        return $phone;
    }

    protected function hasValidSignature(Request $request): bool
    {
        $verificationEnabled = (bool) config(
            'services.whatsapp.verify_webhook_signature',
            false
        );

        if (! $verificationEnabled) {
            return true;
        }

        $appSecret = (string) config(
            'services.whatsapp.app_secret'
        );

        if ($appSecret === '') {
            Log::error(
                'WhatsApp signature verification is enabled but WHATSAPP_APP_SECRET is missing.'
            );

            AuditLogService::system(
                action: 'whatsapp_webhook.app_secret_missing',
                description: 'WhatsApp signature verification is enabled but the app secret is missing.',
            );

            return false;
        }

        $signatureHeader = (string) $request->header(
            'X-Hub-Signature-256',
            ''
        );

        if (! str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $receivedSignature = substr($signatureHeader, 7);

        $expectedSignature = hash_hmac(
            'sha256',
            $request->getContent(),
            $appSecret
        );

        return hash_equals(
            $expectedSignature,
            $receivedSignature
        );
    }

    protected function incomingMessageAlreadyProcessed(
        string $messageId
    ): bool {
        if ($messageId === '' || ! Schema::hasTable('message_logs')) {
            return false;
        }

        $columns = Schema::getColumnListing('message_logs');

        foreach ([
            'provider_message_id',
            'message_id',
            'wamid',
            'external_message_id',
        ] as $column) {
            if (
                in_array($column, $columns, true)
                && DB::table('message_logs')
                    ->where($column, $messageId)
                    ->whereIn('status', ['replied', 'received'])
                    ->exists()
            ) {
                return true;
            }
        }

        return false;
    }

    protected function recordIncomingMessage(
        Invitee $invitee,
        string $messageId,
        string $fromPhone,
        string $messageType,
        string $buttonPayload,
        ?string $buttonTitle,
        string $logType = 'rsvp_reply',
    ): void {
        if (! Schema::hasTable('message_logs')) {
            return;
        }

        $columns = Schema::getColumnListing('message_logs');
        $now = now();

        $row = [
            'event_id' => $invitee->event_id,
            'invitee_id' => $invitee->id,
            'channel' => 'whatsapp',
            'type' => $logType,
            'message_type' => $logType,
            'recipient' => $fromPhone,
            'phone' => $fromPhone,
            'from' => $fromPhone,
            'message' => $buttonTitle ?: $buttonPayload,
            'body' => $buttonTitle ?: $buttonPayload,
            'status' => 'replied',
            'provider' => 'WhatsApp Cloud API',
            'provider_name' => 'WhatsApp Cloud API',
            'provider_status' => 'received',
            'provider_message_id' => $messageId,
            'message_id' => $messageId,
            'wamid' => $messageId,
            'last_reply_message' => $buttonTitle ?: $buttonPayload,
            'meta' => json_encode([
                'message_type' => $messageType,
                'button_payload' => $buttonPayload,
                'button_title' => $buttonTitle,
            ]),
            'provider_response' => json_encode([
                'message_type' => $messageType,
                'button_payload' => $buttonPayload,
                'button_title' => $buttonTitle,
            ]),
            'received_at' => $now,
            'replied_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $insertable = Arr::only($row, $columns);

        if ($insertable !== []) {
            DB::table('message_logs')->insert($insertable);
        }
    }

    protected function findWhatsappMessageLog(
        string $messageId
    ): ?object {
        if (! Schema::hasTable('message_logs')) {
            return null;
        }

        $columns = Schema::getColumnListing('message_logs');

        return DB::table('message_logs')
            ->where(function ($query) use ($columns, $messageId) {
                foreach ([
                    'provider_message_id',
                    'message_id',
                    'wamid',
                    'external_message_id',
                ] as $column) {
                    if (in_array($column, $columns, true)) {
                        $query->orWhere($column, $messageId);
                    }
                }
            })
            ->latest('id')
            ->first();
    }

    protected function updateWhatsappMessageLogs(
        string $messageId,
        string $normalizedStatus,
        string $providerStatus,
        Carbon $timestamp,
        ?string $error,
        array $rawStatus,
    ): void {
        if (! Schema::hasTable('message_logs')) {
            return;
        }

        $columns = Schema::getColumnListing('message_logs');

        $update = [
            'status' => $normalizedStatus,
            'provider_status' => $providerStatus,
            'provider_response' => json_encode($rawStatus),
            'response' => json_encode($rawStatus),
            'meta' => json_encode($rawStatus),
            'error_message' => $error,
            'error' => $error,
            'sent_at' => in_array(
                $normalizedStatus,
                ['sent', 'delivered', 'read'],
                true
            ) ? $timestamp : null,
            'delivered_at' => in_array(
                $normalizedStatus,
                ['delivered', 'read'],
                true
            ) ? $timestamp : null,
            'read_at' => $normalizedStatus === 'read'
                ? $timestamp
                : null,
            'failed_at' => $normalizedStatus === 'failed'
                ? $timestamp
                : null,
            'updated_at' => now(),
        ];

        $safeUpdate = Arr::only($update, $columns);

        if ($safeUpdate === []) {
            return;
        }

        DB::table('message_logs')
            ->where(function ($query) use ($columns, $messageId) {
                foreach ([
                    'provider_message_id',
                    'message_id',
                    'wamid',
                    'external_message_id',
                ] as $column) {
                    if (in_array($column, $columns, true)) {
                        $query->orWhere($column, $messageId);
                    }
                }
            })
            ->update($safeUpdate);
    }

    protected function updateInviteeWhatsappStatus(
        Invitee $invitee,
        string $status,
        string $messageId,
        ?string $error,
        Carbon $timestamp,
    ): void {
        $updates = [
            'last_message_channel' => 'whatsapp',
            'last_message_status' => $status,
            'message_status' => $status,
            'whatsapp_status' => $status,
            'whatsapp_message_id' => $messageId,
            'whatsapp_error' => $error,
            'delivered_at' => in_array(
                $status,
                ['delivered', 'read'],
                true
            ) ? $timestamp : null,
            'failed_at' => $status === 'failed'
                ? $timestamp
                : null,
        ];

        $columns = Schema::getColumnListing(
            $invitee->getTable()
        );

        $safeUpdates = Arr::only($updates, $columns);

        if ($safeUpdates !== []) {
            $invitee->forceFill($safeUpdates)->saveQuietly();
        }
    }

    protected function normalizeWhatsappStatus(
        string $status
    ): string {
        return match (strtolower(trim($status))) {
            'sent' => 'sent',
            'delivered' => 'delivered',
            'read' => 'read',
            'failed' => 'failed',
            default => 'unknown',
        };
    }

    protected function parseWhatsappTimestamp(
        mixed $timestamp
    ): Carbon {
        if (is_numeric($timestamp)) {
            return Carbon::createFromTimestamp(
                (int) $timestamp
            );
        }

        if (filled($timestamp)) {
            try {
                return Carbon::parse($timestamp);
            } catch (Throwable) {
                // Use the current time below.
            }
        }

        return now();
    }

    protected function extractWhatsappError(
        array $status
    ): ?string {
        $errors = $status['errors'] ?? [];

        if (! is_array($errors) || $errors === []) {
            return null;
        }

        return collect($errors)
            ->map(function ($error): string {
                if (! is_array($error)) {
                    return (string) $error;
                }

                return (string) (
                    $error['message']
                    ?? data_get($error, 'error_data.details')
                    ?? $error['title']
                    ?? 'WhatsApp message failed.'
                );
            })
            ->filter()
            ->implode(' | ');
    }
}
