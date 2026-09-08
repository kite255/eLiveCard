<?php

namespace App\Http\Controllers;

use App\Models\Invitee;
use App\Services\AuditLogService;
use App\Services\RsvpService;
use App\Services\WhatsAppApiCloudService;
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
        WhatsAppApiCloudService $whatsAppService
    ): JsonResponse {
        if (! $this->hasValidSignature($request)) {
            Log::warning(
                'WhatsApp webhook rejected because of invalid signature.'
            );

            AuditLogService::system(
                action: 'whatsapp_webhook.invalid_signature',
                description: 'WhatsApp webhook request was rejected because its signature was invalid.',
                metadata: [
                    'signature_present' => filled(
                        $request->header(
                            (string) config(
                                'services.whatsapp.webhook_signature_header',
                                'X-Hub-Signature-256'
                            )
                        )
                    ),
                ],
            );

            return response()->json([
                'message' => 'Invalid webhook signature.',
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }

        $payload = $request->json()->all();

        if (($payload['object'] ?? null) !== 'whatsapp_business_account') {
            Log::warning(
                'Unsupported WhatsApp webhook object received.',
                [
                    'object' => $payload['object'] ?? null,
                ]
            );

            return response()->json([
                'received' => true,
            ], SymfonyResponse::HTTP_OK);
        }

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
                if (($change['field'] ?? 'messages') !== 'messages') {
                    continue;
                }

                $value = is_array($change['value'] ?? null)
                    ? $change['value']
                    : [];

                $sourceMetadata = [
                    'display_phone_number' => data_get(
                        $value,
                        'metadata.display_phone_number'
                    ),
                    'phone_number_id' => data_get(
                        $value,
                        'metadata.phone_number_id'
                    ),
                ];

                $this->validateWebhookPhoneNumber(
                    $sourceMetadata
                );

                foreach ($value['messages'] ?? [] as $message) {
                    try {
                        $this->handleIncomingMessage(
                            message: $message,
                            rsvpService: $rsvpService,
                            whatsAppService: $whatsAppService,
                        );

                        $messagesProcessed++;
                    } catch (Throwable $exception) {
                        $errors++;

                        Log::error(
                            'WhatsApp incoming message processing failed.',
                            [
                                'message_id' => $message['id'] ?? null,
                                'error' => $exception->getMessage(),
                                'exception' => $exception::class,
                            ]
                        );

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
                        $this->handleMessageStatus(
                            status: $status,
                            sourceMetadata: $sourceMetadata,
                        );

                        $statusesProcessed++;
                    } catch (Throwable $exception) {
                        $errors++;

                        Log::error(
                            'WhatsApp status processing failed.',
                            [
                                'message_id' => $status['id'] ?? null,
                                'status' => $status['status'] ?? null,
                                'error' => $exception->getMessage(),
                            ]
                        );

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

    protected function validateWebhookPhoneNumber(
        array $sourceMetadata
    ): void {
        Log::info(
            'WhatsApp webhook source metadata.',
            $sourceMetadata
        );

        $configuredPhoneNumberId = trim(
            (string) config(
                'services.whatsapp.phone_number_id'
            )
        );

        $webhookPhoneNumberId = trim(
            (string) (
                $sourceMetadata['phone_number_id']
                ?? ''
            )
        );

        if (
            $configuredPhoneNumberId !== ''
            && $webhookPhoneNumberId !== ''
            && ! hash_equals(
                $configuredPhoneNumberId,
                $webhookPhoneNumberId
            )
        ) {
            Log::warning(
                'WhatsApp webhook phone number ID does not match the configured sender.',
                [
                    'configured_phone_number_id' =>
                        $configuredPhoneNumberId,

                    'webhook_phone_number_id' =>
                        $webhookPhoneNumberId,

                    'display_phone_number' =>
                        $sourceMetadata['display_phone_number']
                        ?? null,
                ]
            );

            AuditLogService::system(
                action: 'whatsapp_webhook.phone_number_mismatch',
                description: 'The WhatsApp webhook phone number ID did not match the configured sender phone number ID.',
                metadata: [
                    'configured_phone_number_id' =>
                        $configuredPhoneNumberId,

                    'webhook_phone_number_id' =>
                        $webhookPhoneNumberId,

                    'display_phone_number' =>
                        $sourceMetadata['display_phone_number']
                        ?? null,
                ],
            );
        }
    }

    protected function handleIncomingMessage(
        array $message,
        RsvpService $rsvpService,
        WhatsAppApiCloudService $whatsAppService
    ): void {
        $fromPhone = $this->normalizePhone(
            (string) ($message['from'] ?? '')
        );

        $messageId = trim(
            (string) ($message['id'] ?? '')
        );

        $messageType = (string) (
            $message['type'] ?? 'unknown'
        );

        [$replyPayload, $replyTitle] =
            $this->extractIncomingReply($message);

        if ($fromPhone === '') {
            Log::warning(
                'WhatsApp incoming message ignored because phone number is missing.',
                [
                    'message_id' => $messageId,
                    'type' => $messageType,
                ]
            );

            return;
        }

        /*
         * New eLive Card payload format:
         *
         * rsvp_attending:ABC123
         * rsvp_not_attending:ABC123
         */
        $parsedReply = $this->parseReplyPayload(
            $replyPayload
        );

        $canonicalAction =
            $parsedReply['action']
            ?? $this->resolveReplyAction(
                payload: $replyPayload,
                title: $replyTitle,
            );

        $shortCode =
            $parsedReply['short_code']
            ?? null;

        /*
         * Prefer short_code for RSVP button replies.
         * Phone lookup remains as fallback for older templates.
         */
        $invitee = $shortCode
            ? $this->findInviteeByShortCode(
                $shortCode
            )
            : null;

        if (! $invitee) {
            $invitee = $this->findInviteeByPhone(
                $fromPhone
            );
        }

        if (! $invitee) {
            Log::warning(
                'WhatsApp reply ignored because invitee was not found.',
                [
                    'from' => $fromPhone,
                    'short_code' => $shortCode,
                    'reply_payload' => $replyPayload,
                    'reply_title' => $replyTitle,
                    'message_id' => $messageId,
                ]
            );

            AuditLogService::system(
                action: 'whatsapp_message.invitee_not_found',
                description: 'WhatsApp reply was ignored because no invitee could be resolved.',
                metadata: [
                    'from' => $fromPhone,
                    'short_code' => $shortCode,
                    'message_id' => $messageId,
                    'message_type' => $messageType,
                    'reply_payload' => $replyPayload,
                    'reply_title' => $replyTitle,
                ],
            );

            return;
        }

        /*
         * If the short code found an invitee, ensure the incoming
         * WhatsApp number belongs to that invitee.
         */
        if (
            $shortCode
            && ! $this->phoneMatchesInvitee(
                $invitee,
                $fromPhone
            )
        ) {
            Log::warning(
                'WhatsApp RSVP sender did not match the invitee phone number.',
                [
                    'invitee_id' => $invitee->id,
                    'short_code' => $shortCode,
                    'incoming_phone' => $fromPhone,
                ]
            );

            AuditLogService::record(
                action: 'whatsapp_rsvp.phone_mismatch',
                subject: $invitee,
                eventId: $invitee->event_id,
                description: 'A WhatsApp RSVP response was rejected because the sender phone number did not match the invitee.',
                metadata: [
                    'short_code' => $shortCode,
                    'incoming_phone' => $fromPhone,
                    'message_id' => $messageId,
                    'reply_payload' => $replyPayload,
                ],
            );

            return;
        }

        if (
            $this->incomingMessageAlreadyProcessed(
                $messageId
            )
        ) {
            Log::info(
                'Duplicate WhatsApp message ignored.',
                [
                    'message_id' => $messageId,
                    'invitee_id' => $invitee->id,
                ]
            );

            return;
        }

        if (
            $replyPayload === null
            && $replyTitle === null
        ) {
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

        /*
         * Legacy location quick reply support.
         *
         * The current invitation template uses a URL button:
         * /l/{shortCode}
         *
         * Therefore LOCATION/ENEO normally does not arrive here.
         */
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

        if (
            ! in_array(
                $canonicalAction,
                [
                    'rsvp_attending',
                    'rsvp_not_attending',
                ],
                true
            )
        ) {
            $this->recordIncomingMessage(
                invitee: $invitee,
                messageId: $messageId,
                fromPhone: $fromPhone,
                messageType: $messageType,
                buttonPayload:
                    $replyPayload
                    ?? $replyTitle
                    ?? 'unknown',

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
                    'short_code' => $shortCode,
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

        /*
         * RsvpService continues receiving the canonical values it
         * already understands:
         *
         * rsvp_attending
         * rsvp_not_attending
         */
        $updatedInvitee =
            $rsvpService->updateFromWhatsappButton(
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
                'short_code' => $shortCode,
                'reply_payload' => $replyPayload,
                'reply_title' => $replyTitle,
                'canonical_action' => $canonicalAction,
                'source' => 'whatsapp_webhook',
            ],
        );

        Log::info(
            'WhatsApp RSVP reply processed.',
            [
                'invitee_id' => $updatedInvitee->id,
                'event_id' => $updatedInvitee->event_id,
                'phone' => $fromPhone,
                'short_code' => $shortCode,
                'canonical_action' => $canonicalAction,
                'before_status' =>
                    $beforeValues['rsvp_status']
                    ?? null,

                'after_status' =>
                    $updatedInvitee->rsvp_status,
            ]
        );
    }

    /**
     * Extract the action and short code from a new eLive RSVP payload.
     *
     * Examples:
     *
     * rsvp_attending:ABC123
     * rsvp_not_attending:ABC123
     */
    protected function parseReplyPayload(
        ?string $payload
    ): array {
        if (blank($payload)) {
            return [
                'action' => null,
                'short_code' => null,
            ];
        }

        $payload = trim($payload);

        foreach ([
            'rsvp_attending' =>
                'rsvp_attending',

            'rsvp_not_attending' =>
                'rsvp_not_attending',

        ] as $prefix => $action) {
            $needle = $prefix.':';

            if (
                str_starts_with(
                    $payload,
                    $needle
                )
            ) {
                $shortCode = trim(
                    substr(
                        $payload,
                        strlen($needle)
                    )
                );

                return [
                    'action' => $action,
                    'short_code' =>
                        $shortCode !== ''
                            ? $shortCode
                            : null,
                ];
            }
        }

        return [
            'action' => null,
            'short_code' => null,
        ];
    }

    protected function extractIncomingReply(
        array $message
    ): array {
        $payload = data_get(
            $message,
            'interactive.button_reply.id'
        );

        $title = data_get(
            $message,
            'interactive.button_reply.title'
        );

        if (
            ! filled($payload)
            && ! filled($title)
        ) {
            $payload = data_get(
                $message,
                'interactive.list_reply.id'
            );

            $title = data_get(
                $message,
                'interactive.list_reply.title'
            );
        }

        /*
         * Template quick reply buttons are commonly delivered here.
         */
        if (
            ! filled($payload)
            && ! filled($title)
        ) {
            $payload = data_get(
                $message,
                'button.payload'
            );

            $title = data_get(
                $message,
                'button.text'
            );
        }

        if (
            ! filled($payload)
            && ! filled($title)
        ) {
            $text = data_get(
                $message,
                'text.body'
            );

            if (filled($text)) {
                $payload = $text;
                $title = $text;
            }
        }

        return [
            filled($payload)
                ? trim((string) $payload)
                : null,

            filled($title)
                ? trim((string) $title)
                : null,
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
            'nita_hudhuria',
            'nita_huduria'
                => 'rsvp_attending',

            'rsvp_not_attending',
            'not_attending',
            'no',
            'hapana',
            'sitaweza_hudhuria',
            'sitaweza_kuhudhuria',
            'sitaweza_huduria',
            'sita_hudhuria',
            'sita_kuhudhuria',
            'sitahudhuria',
            'sitahuduria'
                => 'rsvp_not_attending',

            'location',
            'eneo',
            'view_location',
            'open_location',
            'fungua_location',
            'angalia_mahali',
            'mahali',
            'ramani'
                => 'location',

            default => null,
        };
    }

    protected function normalizeReplyValue(
        string $value
    ): string {
        $value = mb_strtolower(
            trim($value)
        );

        $value = preg_replace(
            '/[\s\-]+/',
            '_',
            $value
        ) ?: '';

        return trim(
            $value,
            '_'
        );
    }

    protected function findInviteeByShortCode(
        string $shortCode
    ): ?Invitee {
        $shortCode = trim($shortCode);

        if ($shortCode === '') {
            return null;
        }

        return Invitee::query()
            ->where(
                'short_code',
                $shortCode
            )
            ->first();
    }

    protected function findInviteeByPhone(
        string $phone
    ): ?Invitee {
        $normalizedPhone =
            $this->normalizePhone($phone);

        if ($normalizedPhone === '') {
            return null;
        }

        return Invitee::query()
            ->where(
                function ($query) use (
                    $normalizedPhone
                ) {
                    $query
                        ->where(
                            'phone',
                            $normalizedPhone
                        )
                        ->orWhere(
                            'phone',
                            '+'.$normalizedPhone
                        )
                        ->orWhereRaw(
                            "REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', '') = ?",
                            [$normalizedPhone]
                        );
                }
            )
            ->latest('id')
            ->first();
    }

    protected function phoneMatchesInvitee(
        Invitee $invitee,
        string $incomingPhone
    ): bool {
        $storedPhone = $this->normalizePhone(
            (string) $invitee->phone
        );

        $incomingPhone = $this->normalizePhone(
            $incomingPhone
        );

        if (
            $storedPhone === ''
            || $incomingPhone === ''
        ) {
            return false;
        }

        return hash_equals(
            $storedPhone,
            $incomingPhone
        );
    }

    protected function handleLocationReply(
        Invitee $invitee,
        WhatsAppApiCloudService $whatsappService,
        string $messageId,
        string $fromPhone,
        string $messageType,
        ?string $replyPayload,
        ?string $replyTitle,
    ): void {
        $invitee->loadMissing('event');

        $event = $invitee->event;

        $locationUrl = trim(
            (string) (
                $event?->google_maps_link
                ?? ''
            )
        );

        $eventName =
            $event?->title
            ?? $event?->name
            ?? 'tukio';

        $responseMessage =
            $locationUrl !== ''
                ? "Mahali pa {$eventName}:\n{$locationUrl}"
                : 'Samahani, kiungo cha Google Maps cha tukio hili bado hakijawekwa.';

        $whatsappService->sendText(
            phone: (string) $invitee->phone,
            message: $responseMessage,
        );

        $columns = Schema::getColumnListing(
            $invitee->getTable()
        );

        $invitee
            ->forceFill(
                Arr::only(
                    [
                        'last_message_channel' =>
                            'whatsapp',

                        'last_message_status' =>
                            'replied',

                        'last_reply_message' =>
                            $replyTitle
                            ?: $replyPayload,

                        'last_reply_at' =>
                            now(),
                    ],
                    $columns
                )
            )
            ->saveQuietly();

        $this->recordIncomingMessage(
            invitee: $invitee,
            messageId: $messageId,
            fromPhone: $fromPhone,
            messageType: $messageType,
            buttonPayload:
                $replyPayload
                ?? 'location',

            buttonTitle: $replyTitle,
            logType: 'location_request',
        );

        AuditLogService::record(
            action: 'whatsapp_location.sent',
            subject: $invitee,
            eventId: $invitee->event_id,
            description:
                $locationUrl !== ''
                    ? 'The event location was sent after a WhatsApp request.'
                    : 'A WhatsApp location request was received, but the event had no Google Maps link.',
            metadata: [
                'message_id' => $messageId,
                'from' => $fromPhone,
                'location_available' =>
                    $locationUrl !== '',
            ],
        );
    }

    protected function handleMessageStatus(
        array $status,
        array $sourceMetadata = [],
    ): void {
        $messageId = trim(
            (string) ($status['id'] ?? '')
        );

        $recipient = $this->normalizePhone(
            (string) (
                $status['recipient_id']
                ?? ''
            )
        );

        $providerStatus = strtolower(
            trim(
                (string) (
                    $status['status']
                    ?? 'unknown'
                )
            )
        );

        $timestamp =
            $this->parseWhatsappTimestamp(
                $status['timestamp']
                ?? null
            );

        $error =
            $this->extractWhatsappError(
                $status
            );

        Log::info(
            'WhatsApp message status received.',
            [
                'message_id' => $messageId,
                'recipient_id' => $recipient,
                'status' => $providerStatus,
                'timestamp' =>
                    $status['timestamp']
                    ?? null,

                'display_phone_number' =>
                    $sourceMetadata[
                        'display_phone_number'
                    ] ?? null,

                'phone_number_id' =>
                    $sourceMetadata[
                        'phone_number_id'
                    ] ?? null,
            ]
        );

        if ($messageId === '') {
            return;
        }

        $matchedLog =
            $this->findWhatsappMessageLog(
                $messageId
            );

        if (! $matchedLog) {
            $recentCandidateIds =
                $this->recentWhatsappMessageCandidates(
                    recipient: $recipient,
                );

            Log::warning(
                'WhatsApp status could not be matched to an outgoing message log.',
                [
                    'message_id' => $messageId,
                    'recipient_id' => $recipient,
                    'status' => $providerStatus,
                    'recent_candidate_ids' =>
                        $recentCandidateIds,

                    'error' => $error,
                ]
            );

            return;
        }

        $incomingStatus =
            $this->normalizeWhatsappStatus(
                $providerStatus
            );

        $normalizedStatus =
            $this->resolveEffectiveWhatsappStatus(
                currentStatus:
                    (string) (
                        $matchedLog->status
                        ?? ''
                    ),

                incomingStatus:
                    $incomingStatus,
            );

        $this->updateWhatsappMessageLogs(
            messageId: $messageId,
            normalizedStatus: $normalizedStatus,
            providerStatus: $providerStatus,
            timestamp: $timestamp,
            error: $error,
            rawStatus: $status,
        );

        $invitee =
            ! empty($matchedLog->invitee_id)
                ? Invitee::find(
                    $matchedLog->invitee_id
                )
                : null;

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
                metadata: [
                    'message_id' => $messageId,
                    'recipient_id' => $recipient,
                    'provider_status' =>
                        $providerStatus,

                    'normalized_status' =>
                        $normalizedStatus,

                    'error' => $error,
                    'source' =>
                        'whatsapp_webhook',
                ],
            );
        }
    }

    protected function normalizePhone(
        string $phone
    ): string {
        $phone = preg_replace(
            '/\D+/',
            '',
            $phone
        ) ?: '';

        if ($phone === '') {
            return '';
        }

        if (str_starts_with($phone, '0')) {
            return '255'.substr(
                $phone,
                1
            );
        }

        if (
            ! str_starts_with(
                $phone,
                '255'
            )
            && strlen($phone) === 9
        ) {
            return '255'.$phone;
        }

        return $phone;
    }

    protected function hasValidSignature(
        Request $request
    ): bool {
        $verificationEnabled =
            filter_var(
                config(
                    'services.whatsapp.verify_webhook_signature',
                    true
                ),
                FILTER_VALIDATE_BOOL
            );

        if (! $verificationEnabled) {
            return true;
        }

        $appSecret = trim(
            (string) config(
                'services.whatsapp.app_secret'
            )
        );

        if ($appSecret === '') {
            Log::error(
                'WhatsApp signature verification is enabled but WHATSAPP_APP_SECRET is missing.'
            );

            return false;
        }

        $signatureHeaderName = trim(
            (string) config(
                'services.whatsapp.webhook_signature_header',
                'X-Hub-Signature-256'
            )
        );

        $signatureHeader = trim(
            (string) $request->header(
                $signatureHeaderName,
                ''
            )
        );

        if (
            ! str_starts_with(
                $signatureHeader,
                'sha256='
            )
        ) {
            return false;
        }

        $receivedSignature = strtolower(
            trim(
                substr(
                    $signatureHeader,
                    7
                )
            )
        );

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
        if (
            $messageId === ''
            || ! Schema::hasTable(
                'message_logs'
            )
        ) {
            return false;
        }

        $columns =
            Schema::getColumnListing(
                'message_logs'
            );

        foreach ([
            'provider_message_id',
            'message_id',
            'wamid',
            'external_message_id',
        ] as $column) {
            if (
                in_array(
                    $column,
                    $columns,
                    true
                )
                && DB::table('message_logs')
                    ->where(
                        $column,
                        $messageId
                    )
                    ->whereIn(
                        'status',
                        [
                            'replied',
                            'received',
                        ]
                    )
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
        if (
            ! Schema::hasTable(
                'message_logs'
            )
        ) {
            return;
        }

        $columns =
            Schema::getColumnListing(
                'message_logs'
            );

        $now = now();

        $row = [
            'event_id' =>
                $invitee->event_id,

            'invitee_id' =>
                $invitee->id,

            'channel' =>
                'whatsapp',

            'type' =>
                $logType,

            'message_type' =>
                $logType,

            'recipient' =>
                $fromPhone,

            'phone' =>
                $fromPhone,

            'from' =>
                $fromPhone,

            'message' =>
                $buttonTitle
                ?: $buttonPayload,

            'body' =>
                $buttonTitle
                ?: $buttonPayload,

            'status' =>
                'replied',

            'provider' =>
                'WhatsApp Cloud API',

            'provider_name' =>
                'WhatsApp Cloud API',

            'provider_status' =>
                'received',

            'provider_message_id' =>
                $messageId,

            'message_id' =>
                $messageId,

            'wamid' =>
                $messageId,

            'last_reply_message' =>
                $buttonTitle
                ?: $buttonPayload,

            'meta' => json_encode([
                'message_type' =>
                    $messageType,

                'button_payload' =>
                    $buttonPayload,

                'button_title' =>
                    $buttonTitle,
            ]),

            'provider_response' =>
                json_encode([
                    'message_type' =>
                        $messageType,

                    'button_payload' =>
                        $buttonPayload,

                    'button_title' =>
                        $buttonTitle,
                ]),

            'received_at' =>
                $now,

            'replied_at' =>
                $now,

            'created_at' =>
                $now,

            'updated_at' =>
                $now,
        ];

        $insertable =
            Arr::only(
                $row,
                $columns
            );

        if ($insertable !== []) {
            DB::table(
                'message_logs'
            )->insert(
                $insertable
            );
        }
    }

    protected function recentWhatsappMessageCandidates(
        string $recipient,
        int $limit = 5,
    ): array {
        if (
            $recipient === ''
            || ! Schema::hasTable(
                'message_logs'
            )
        ) {
            return [];
        }

        $columns =
            Schema::getColumnListing(
                'message_logs'
            );

        if (
            ! in_array(
                'phone',
                $columns,
                true
            )
            || ! in_array(
                'provider_message_id',
                $columns,
                true
            )
        ) {
            return [];
        }

        $select =
            array_values(
                array_intersect(
                    [
                        'id',
                        'invitee_id',
                        'phone',
                        'status',
                        'provider_message_id',
                        'sent_at',
                        'created_at',
                    ],
                    $columns
                )
            );

        $query =
            DB::table('message_logs')
                ->where(
                    'phone',
                    $recipient
                )
                ->whereNotNull(
                    'provider_message_id'
                );

        if (
            in_array(
                'channel',
                $columns,
                true
            )
        ) {
            $query->where(
                'channel',
                'whatsapp'
            );
        }

        return $query
            ->orderByDesc('id')
            ->limit(
                max(
                    1,
                    min($limit, 20)
                )
            )
            ->get($select)
            ->map(
                fn (object $row): array =>
                    (array) $row
            )
            ->all();
    }

    protected function findWhatsappMessageLog(
        string $messageId
    ): ?object {
        if (
            ! Schema::hasTable(
                'message_logs'
            )
        ) {
            return null;
        }

        $columns =
            Schema::getColumnListing(
                'message_logs'
            );

        $messageIdColumns =
            array_values(
                array_intersect(
                    [
                        'provider_message_id',
                        'message_id',
                        'wamid',
                        'external_message_id',
                    ],
                    $columns
                )
            );

        if ($messageIdColumns === []) {
            return null;
        }

        return DB::table(
            'message_logs'
        )
            ->where(
                function ($query) use (
                    $messageIdColumns,
                    $messageId
                ) {
                    foreach (
                        $messageIdColumns
                        as $column
                    ) {
                        $query->orWhere(
                            $column,
                            $messageId
                        );
                    }
                }
            )
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
        if (
            ! Schema::hasTable(
                'message_logs'
            )
        ) {
            return;
        }

        $columns =
            Schema::getColumnListing(
                'message_logs'
            );

        $messageIdColumns =
            array_values(
                array_intersect(
                    [
                        'provider_message_id',
                        'message_id',
                        'wamid',
                        'external_message_id',
                    ],
                    $columns
                )
            );

        if ($messageIdColumns === []) {
            return;
        }

        $update = [
            'status' =>
                $normalizedStatus,

            'provider_status' =>
                $providerStatus,

            'provider_response' =>
                json_encode($rawStatus),

            'response' =>
                json_encode($rawStatus),

            'error_message' =>
                $error,

            'error' =>
                $error,

            'updated_at' =>
                now(),
        ];

        if (
            in_array(
                $normalizedStatus,
                [
                    'sent',
                    'delivered',
                    'read',
                ],
                true
            )
        ) {
            $update['sent_at'] =
                $timestamp;
        }

        if (
            in_array(
                $normalizedStatus,
                [
                    'delivered',
                    'read',
                ],
                true
            )
        ) {
            $update['delivered_at'] =
                $timestamp;
        }

        if (
            $normalizedStatus ===
            'read'
        ) {
            $update['read_at'] =
                $timestamp;
        }

        if (
            $normalizedStatus ===
            'failed'
        ) {
            $update['failed_at'] =
                $timestamp;
        }

        $safeUpdate =
            Arr::only(
                $update,
                $columns
            );

        if ($safeUpdate === []) {
            return;
        }

        DB::table('message_logs')
            ->where(
                function ($query) use (
                    $messageIdColumns,
                    $messageId
                ) {
                    foreach (
                        $messageIdColumns
                        as $column
                    ) {
                        $query->orWhere(
                            $column,
                            $messageId
                        );
                    }
                }
            )
            ->update(
                $safeUpdate
            );
    }

    protected function updateInviteeWhatsappStatus(
        Invitee $invitee,
        string $status,
        string $messageId,
        ?string $error,
        Carbon $timestamp,
    ): void {
        $currentStatus =
            $this->normalizeWhatsappStatus(
                (string) (
                    $invitee->whatsapp_status
                    ?? $invitee->last_message_status
                    ?? ''
                )
            );

        $effectiveStatus =
            $this->resolveEffectiveWhatsappStatus(
                currentStatus:
                    $currentStatus,

                incomingStatus:
                    $status,
            );

        $updates = [
            'last_message_channel' =>
                'whatsapp',

            'last_message_status' =>
                $effectiveStatus,

            'message_status' =>
                $effectiveStatus,

            'whatsapp_status' =>
                $effectiveStatus,

            'whatsapp_message_id' =>
                $messageId,

            'last_message_error' =>
                $effectiveStatus === 'failed'
                    ? $error
                    : null,
        ];

        if (
            in_array(
                $effectiveStatus,
                [
                    'sent',
                    'delivered',
                    'read',
                ],
                true
            )
        ) {
            $updates['whatsapp_sent_at'] =
                $invitee->whatsapp_sent_at
                ?: $timestamp;
        }

        if (
            in_array(
                $effectiveStatus,
                [
                    'delivered',
                    'read',
                ],
                true
            )
        ) {
            $updates['whatsapp_delivered_at'] =
                $timestamp;
        }

        if (
            $effectiveStatus ===
            'read'
        ) {
            $updates['whatsapp_read_at'] =
                $timestamp;
        }

        if (
            $effectiveStatus ===
            'failed'
        ) {
            $updates['whatsapp_failed_at'] =
                $timestamp;
        }

        $columns =
            Schema::getColumnListing(
                $invitee->getTable()
            );

        $safeUpdates =
            Arr::only(
                $updates,
                $columns
            );

        if ($safeUpdates !== []) {
            $invitee
                ->forceFill(
                    $safeUpdates
                )
                ->saveQuietly();
        }
    }

    protected function normalizeWhatsappStatus(
        string $status
    ): string {
        return match (
            strtolower(
                trim($status)
            )
        ) {
            'submitted' => 'sent',
            'accepted' => 'sent',
            'sent' => 'sent',
            'delivered' => 'delivered',
            'read' => 'read',
            'failed' => 'failed',
            default => 'unknown',
        };
    }

    protected function resolveEffectiveWhatsappStatus(
        string $currentStatus,
        string $incomingStatus,
    ): string {
        $currentStatus =
            $this->normalizeWhatsappStatus(
                $currentStatus
            );

        $incomingStatus =
            $this->normalizeWhatsappStatus(
                $incomingStatus
            );

        if (
            $incomingStatus ===
            'unknown'
        ) {
            return $currentStatus !==
                'unknown'
                    ? $currentStatus
                    : 'unknown';
        }

        if (
            $incomingStatus ===
            'failed'
        ) {
            return 'failed';
        }

        if (
            $currentStatus ===
            'failed'
        ) {
            return 'failed';
        }

        $rank = [
            'unknown' => 0,
            'sent' => 10,
            'delivered' => 20,
            'read' => 30,
        ];

        return (
            $rank[$incomingStatus]
            ?? 0
        ) >= (
            $rank[$currentStatus]
            ?? 0
        )
            ? $incomingStatus
            : $currentStatus;
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
                return Carbon::parse(
                    $timestamp
                );
            } catch (Throwable) {
                //
            }
        }

        return now();
    }

    protected function extractWhatsappError(
        array $status
    ): ?string {
        $errors =
            $status['errors']
            ?? [];

        if (
            ! is_array($errors)
            || $errors === []
        ) {
            return null;
        }

        return collect($errors)
            ->map(
                function ($error): string {
                    if (
                        ! is_array($error)
                    ) {
                        return (string) $error;
                    }

                    return (string) (
                        $error['message']
                        ?? data_get(
                            $error,
                            'error_data.details'
                        )
                        ?? $error['title']
                        ?? 'WhatsApp message failed.'
                    );
                }
            )
            ->filter()
            ->implode(' | ');
    }
}