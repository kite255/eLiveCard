<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\Invitee;
use App\Services\AuditLogService;
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
            (new WithoutOverlapping(
                'send-invitation-sms-'.$this->eventId.'-'.$this->inviteeId
            ))
                ->releaseAfter(10)
                ->expireAfter(180),
        ];
    }

    public function handle(SmsService $smsService): void
    {
        $event = Event::find($this->eventId);

        if (! $event) {
            AuditLogService::system(
                action: 'invitation_sms.event_missing',
                description: 'Invitation SMS job stopped because the event could not be found.',
                metadata: [
                    'event_id' => $this->eventId,
                    'invitee_id' => $this->inviteeId,
                    'attempt' => $this->attempts(),
                    'job' => self::class,
                ],
            );

            Log::warning('Invitation SMS skipped because event was not found.', [
                'event_id' => $this->eventId,
                'invitee_id' => $this->inviteeId,
            ]);

            return;
        }

        $invitee = Invitee::query()
            ->with(['event', 'cardType'])
            ->where('event_id', $event->id)
            ->find($this->inviteeId);

        if (! $invitee) {
            AuditLogService::system(
                action: 'invitation_sms.invitee_missing',
                description: 'Invitation SMS job stopped because the invitee could not be found for the event.',
                eventId: $event->id,
                metadata: [
                    'event_id' => $event->id,
                    'invitee_id' => $this->inviteeId,
                    'attempt' => $this->attempts(),
                    'job' => self::class,
                ],
            );

            Log::warning('Invitation SMS skipped because invitee was not found for the event.', [
                'event_id' => $event->id,
                'invitee_id' => $this->inviteeId,
            ]);

            return;
        }

        AuditLogService::record(
            action: 'invitation_sms.job_started',
            subject: $invitee,
            eventId: $event->id,
            description: 'Invitation SMS queue job started.',
            metadata: [
                'invitee_id' => $invitee->id,
                'invitee_name' => $invitee->name,
                'attempt' => $this->attempts(),
                'max_attempts' => $this->tries,
                'job' => self::class,
            ],
        );

        if (blank($invitee->phone)) {
            AuditLogService::record(
                action: 'invitation_sms.phone_missing',
                subject: $invitee,
                eventId: $event->id,
                description: 'Invitation SMS was skipped because the invitee phone number is empty.',
                metadata: [
                    'invitee_id' => $invitee->id,
                    'invitee_name' => $invitee->name,
                ],
            );

            Log::warning('Invitation SMS skipped because invitee phone is missing.', [
                'event_id' => $event->id,
                'invitee_id' => $invitee->id,
            ]);

            return;
        }

        if (blank($invitee->short_code)) {
            AuditLogService::record(
                action: 'invitation_sms.short_code_missing',
                subject: $invitee,
                eventId: $event->id,
                description: 'Invitation SMS was skipped because the private invitation short code is missing.',
                metadata: [
                    'invitee_id' => $invitee->id,
                    'invitee_name' => $invitee->name,
                    'phone' => $invitee->phone,
                ],
            );

            Log::warning('Invitation SMS skipped because short code is missing.', [
                'event_id' => $event->id,
                'invitee_id' => $invitee->id,
            ]);

            return;
        }

        if ($this->alreadySuccessfullySent($invitee)) {
            AuditLogService::record(
                action: 'invitation_sms.duplicate_prevented',
                subject: $invitee,
                eventId: $event->id,
                description: 'Duplicate invitation SMS was prevented.',
                metadata: [
                    'invitee_id' => $invitee->id,
                    'phone' => $invitee->phone,
                    'invitation_sms_status' => $invitee->invitation_sms_status ?? null,
                    'sms_message_id' => $invitee->invitation_sms_message_id
                        ?? $invitee->sms_message_id
                        ?? null,
                ],
            );

            Log::info('Duplicate invitation SMS prevented.', [
                'event_id' => $event->id,
                'invitee_id' => $invitee->id,
            ]);

            return;
        }

        $oldValues = $invitee->only([
            'message_status',
            'last_message_channel',
            'sms_status',
            'sms_message_id',
            'sms_sent_at',
            'sms_error',
            'invitation_sms_status',
            'invitation_sms_message_id',
            'invitation_sms_sent_at',
            'invitation_sms_error',
        ]);

        try {
            /*
            |--------------------------------------------------------------------------
            | Centralized SMS flow
            |--------------------------------------------------------------------------
            | SmsService::sendInvitation() handles:
            | - phone formatting
            | - placeholder rendering
            | - provider request
            | - invitee message status updates
            | - sms_logs and message_logs records
            | - provider response and failure handling
            |
            | Do not manually create another SmsLog here.
            */

            $result = $smsService->sendInvitation($invitee);

            if (! (bool) ($result['success'] ?? false)) {
                throw new \RuntimeException(
                    filled($result['error'] ?? null)
                        ? (string) $result['error']
                        : 'Invitation SMS provider returned a failed response.'
                );
            }

            $freshInvitee = $invitee->fresh(['event', 'cardType']) ?? $invitee;
            $status = (string) ($result['status'] ?? 'sent');

            AuditLogService::messageSent(
                subject: $freshInvitee,
                eventId: $event->id,
                description: $status === 'logged'
                    ? 'Invitation SMS was processed using the log driver.'
                    : 'Invitation SMS was sent successfully.',
                oldValues: $oldValues,
                newValues: $freshInvitee->only([
                    'message_status',
                    'last_message_channel',
                    'sms_status',
                    'sms_message_id',
                    'sms_sent_at',
                    'sms_error',
                    'invitation_sms_status',
                    'invitation_sms_message_id',
                    'invitation_sms_sent_at',
                    'invitation_sms_error',
                ]),
                metadata: [
                    'invitee_id' => $freshInvitee->id,
                    'invitee_name' => $freshInvitee->name,
                    'phone' => $freshInvitee->phone,
                    'sms_type' => 'invitation_card',
                    'status' => $status,
                    'message_id' => $result['message_id'] ?? null,
                    'provider' => $result['provider'] ?? null,
                    'provider_status' => $result['provider_status'] ?? $status,
                    'driver' => $result['driver'] ?? null,
                    'attempt' => $this->attempts(),
                ],
            );

            Log::info('Invitation SMS processed successfully.', [
                'event_id' => $event->id,
                'invitee_id' => $invitee->id,
                'status' => $status,
                'message_id' => $result['message_id'] ?? null,
            ]);
        } catch (Throwable $exception) {
            $freshInvitee = $invitee->fresh(['event', 'cardType']) ?? $invitee;

            AuditLogService::record(
                action: 'invitation_sms.job_failed',
                subject: $freshInvitee,
                eventId: $event->id,
                description: 'Invitation SMS queue job failed.',
                oldValues: $oldValues,
                newValues: $freshInvitee->only([
                    'message_status',
                    'last_message_channel',
                    'sms_status',
                    'sms_message_id',
                    'sms_sent_at',
                    'sms_error',
                    'invitation_sms_status',
                    'invitation_sms_message_id',
                    'invitation_sms_sent_at',
                    'invitation_sms_error',
                ]),
                metadata: [
                    'invitee_id' => $freshInvitee->id,
                    'invitee_name' => $freshInvitee->name,
                    'phone' => $freshInvitee->phone,
                    'attempt' => $this->attempts(),
                    'max_attempts' => $this->tries,
                    'error' => $exception->getMessage(),
                    'exception' => $exception::class,
                    'failure_category' => $this->classifyFailure($exception->getMessage()),
                ],
            );

            Log::error('Invitation SMS job failed.', [
                'event_id' => $event->id,
                'invitee_id' => $invitee->id,
                'phone' => $invitee->phone,
                'attempt' => $this->attempts(),
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    protected function alreadySuccessfullySent(Invitee $invitee): bool
    {
        $statuses = [
            $invitee->invitation_sms_status ?? null,
            $invitee->sms_status ?? null,
        ];

        return collect($statuses)
            ->filter()
            ->contains(
                fn (string $status): bool => in_array(
                    strtolower($status),
                    ['logged', 'accepted', 'sent', 'delivered'],
                    true
                )
            );
    }

    protected function classifyFailure(string $message): string
    {
        $message = strtolower($message);

        return match (true) {
            str_contains($message, 'insufficient credit'),
            str_contains($message, 'insufficient balance'),
            str_contains($message, 'low balance') => 'insufficient_credit',

            str_contains($message, 'invalid tanzania phone'),
            str_contains($message, 'invalid phone'),
            str_contains($message, 'phone number is missing') => 'invalid_phone',

            str_contains($message, 'timeout'),
            str_contains($message, 'timed out') => 'provider_timeout',

            str_contains($message, 'not configured'),
            str_contains($message, 'api key'),
            str_contains($message, 'api secret'),
            str_contains($message, 'sender id'),
            str_contains($message, 'sender_id') => 'configuration_error',

            str_contains($message, 'rejected') => 'provider_rejected',

            str_contains($message, 'http 401'),
            str_contains($message, 'http 403'),
            str_contains($message, 'unauthorized'),
            str_contains($message, 'forbidden') => 'authentication_error',

            str_contains($message, 'http 429'),
            str_contains($message, 'rate limit') => 'rate_limited',

            default => 'unknown',
        };
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('SendInvitationSmsJob permanently failed.', [
            'event_id' => $this->eventId,
            'invitee_id' => $this->inviteeId,
            'error' => $exception?->getMessage(),
        ]);

        $invitee = Invitee::query()
            ->with('event')
            ->where('event_id', $this->eventId)
            ->find($this->inviteeId);

        if (! $invitee) {
            AuditLogService::system(
                action: 'invitation_sms.permanently_failed',
                description: 'Invitation SMS job permanently failed, but the invitee record was unavailable.',
                eventId: $this->eventId,
                metadata: [
                    'event_id' => $this->eventId,
                    'invitee_id' => $this->inviteeId,
                    'max_attempts' => $this->tries,
                    'error' => $exception?->getMessage(),
                    'exception' => $exception ? $exception::class : null,
                ],
            );

            return;
        }

        AuditLogService::record(
            action: 'invitation_sms.permanently_failed',
            subject: $invitee,
            eventId: $invitee->event_id,
            description: 'Invitation SMS job permanently failed after all queue attempts.',
            metadata: [
                'event_id' => $invitee->event_id,
                'invitee_id' => $invitee->id,
                'invitee_name' => $invitee->name,
                'phone' => $invitee->phone,
                'max_attempts' => $this->tries,
                'error' => $exception?->getMessage(),
                'exception' => $exception ? $exception::class : null,
                'failure_category' => $this->classifyFailure(
                    (string) $exception?->getMessage()
                ),
            ],
        );
    }
}
