<?php

namespace App\Jobs;

use App\Models\Invitee;
use App\Services\AuditLogService;
use App\Services\SmsService;
use App\Support\EliveMessagePlaceholders;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SendWelcomeSmsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    /**
     * Retry delays in seconds.
     *
     * The increasing delay avoids repeatedly calling the SMS provider during
     * a short outage.
     *
     * @var array<int, int>
     */
    public array $backoff = [15, 60, 180];

    public function __construct(
        public int $inviteeId
    ) {
        $this->onQueue('messages');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('welcome-sms-invitee-'.$this->inviteeId))
                ->releaseAfter(10)
                ->expireAfter(120),
        ];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(10);
    }

    public function handle(SmsService $smsService): void
    {
        $invitee = Invitee::query()
            ->with(['event', 'cardType'])
            ->find($this->inviteeId);

        if (! $invitee) {
            Log::warning('Welcome SMS skipped because invitee was not found.', [
                'invitee_id' => $this->inviteeId,
            ]);

            AuditLogService::system(
                action: 'welcome_sms.invitee_missing',
                description: 'Welcome SMS job stopped because the invitee could not be found.',
                metadata: [
                    'invitee_id' => $this->inviteeId,
                    'attempt' => $this->attempts(),
                    'job' => self::class,
                ],
            );

            return;
        }

        $event = $invitee->event;

        AuditLogService::record(
            action: 'welcome_sms.job_started',
            subject: $invitee,
            eventId: $invitee->event_id,
            description: 'Welcome SMS queue job started.',
            metadata: [
                'invitee_id' => $invitee->id,
                'invitee_name' => $invitee->name,
                'attempt' => $this->attempts(),
                'max_attempts' => $this->tries,
                'job' => self::class,
            ],
        );

        if (! $event) {
            Log::warning('Welcome SMS skipped because event was not found.', [
                'invitee_id' => $invitee->id,
                'event_id' => $invitee->event_id,
            ]);

            AuditLogService::record(
                action: 'welcome_sms.event_missing',
                subject: $invitee,
                eventId: $invitee->event_id,
                description: 'Welcome SMS was skipped because the event could not be found.',
                metadata: [
                    'invitee_id' => $invitee->id,
                    'attempt' => $this->attempts(),
                ],
            );

            return;
        }

        if (! $this->welcomeSmsEnabled($event)) {
            Log::info('Welcome SMS skipped because it is disabled for the event.', [
                'invitee_id' => $invitee->id,
                'event_id' => $event->id,
            ]);

            AuditLogService::record(
                action: 'welcome_sms.disabled',
                subject: $invitee,
                eventId: $event->id,
                description: 'Welcome SMS was skipped because it is disabled for this event.',
                metadata: [
                    'invitee_id' => $invitee->id,
                    'event_id' => $event->id,
                    'attempt' => $this->attempts(),
                ],
            );

            return;
        }

        $templateMessage = $this->welcomeSmsTemplate($event);
        $message = EliveMessagePlaceholders::render($templateMessage, $invitee);

        if (blank(trim($message))) {
            Log::warning('Welcome SMS skipped because the rendered message is empty.', [
                'invitee_id' => $invitee->id,
                'event_id' => $event->id,
            ]);

            AuditLogService::record(
                action: 'welcome_sms.message_empty',
                subject: $invitee,
                eventId: $event->id,
                description: 'Welcome SMS was skipped because the rendered message was empty.',
                metadata: [
                    'invitee_id' => $invitee->id,
                    'attempt' => $this->attempts(),
                ],
            );

            return;
        }

        $phone = trim((string) ($invitee->phone ?? ''));

        if ($phone === '') {
            $smsLogId = $this->recordSmsLog(
                invitee: $invitee,
                message: $message,
                status: 'failed',
                errorMessage: 'Invitee phone number is empty.',
            );

            Log::warning('Welcome SMS skipped because invitee has no phone number.', [
                'invitee_id' => $invitee->id,
                'event_id' => $event->id,
            ]);

            AuditLogService::record(
                action: 'welcome_sms.phone_missing',
                subject: $invitee,
                eventId: $event->id,
                description: 'Welcome SMS failed because the invitee phone number is empty.',
                metadata: [
                    'invitee_id' => $invitee->id,
                    'sms_log_id' => $smsLogId,
                    'message_length' => mb_strlen($message),
                ],
            );

            return;
        }

        if ($this->alreadySent($invitee->id)) {
            Log::info('Duplicate welcome SMS prevented.', [
                'invitee_id' => $invitee->id,
                'event_id' => $event->id,
            ]);

            AuditLogService::record(
                action: 'welcome_sms.duplicate_prevented',
                subject: $invitee,
                eventId: $event->id,
                description: 'Duplicate welcome SMS was prevented.',
                metadata: [
                    'invitee_id' => $invitee->id,
                    'phone' => $invitee->phone,
                ],
            );

            return;
        }

        try {
            $result = $smsService->sendCustomMessage(
                invitee: $invitee,
                message: $message,
                type: 'welcome_sms',
            );

            $successful = (bool) ($result['success'] ?? false);
            $status = (string) ($result['status'] ?? ($successful ? 'sent' : 'failed'));

            if (! $successful) {
                throw new \RuntimeException(
                    filled($result['error'] ?? null)
                        ? (string) $result['error']
                        : 'Welcome SMS provider returned a failed response.'
                );
            }

            Log::info('Welcome SMS processed successfully.', [
                'invitee_id' => $invitee->id,
                'event_id' => $event->id,
                'status' => $status,
                'message_id' => $result['message_id'] ?? null,
            ]);

            AuditLogService::messageSent(
                subject: $invitee,
                eventId: $event->id,
                description: $status === 'logged'
                    ? 'Welcome SMS was processed using the log driver.'
                    : 'Welcome SMS was sent successfully.',
                metadata: [
                    'invitee_id' => $invitee->id,
                    'invitee_name' => $invitee->name,
                    'phone' => $phone,
                    'sms_type' => 'welcome_sms',
                    'status' => $status,
                    'message_id' => $result['message_id'] ?? null,
                    'provider' => $result['provider'] ?? null,
                    'provider_status' => $result['provider_status'] ?? $status,
                    'driver' => $result['driver'] ?? null,
                    'attempt' => $this->attempts(),
                    'message_length' => mb_strlen($message),
                ],
            );

            AuditLogService::record(
                action: 'welcome_sms.job_completed',
                subject: $invitee,
                eventId: $event->id,
                description: 'Welcome SMS queue job completed successfully.',
                metadata: [
                    'invitee_id' => $invitee->id,
                    'status' => $status,
                    'message_id' => $result['message_id'] ?? null,
                    'attempt' => $this->attempts(),
                ],
            );
        } catch (Throwable $exception) {
            $smsLogId = $this->recordSmsLog(
                invitee: $invitee,
                message: $message,
                status: 'failed',
                errorMessage: $exception->getMessage(),
            );

            Log::error('Welcome SMS job failed.', [
                'invitee_id' => $invitee->id,
                'event_id' => $event->id,
                'error' => $exception->getMessage(),
            ]);

            AuditLogService::record(
                action: 'welcome_sms.job_failed',
                subject: $invitee,
                eventId: $event->id,
                description: 'Welcome SMS queue job failed.',
                metadata: [
                    'invitee_id' => $invitee->id,
                    'invitee_name' => $invitee->name,
                    'phone' => $invitee->phone,
                    'sms_log_id' => $smsLogId,
                    'attempt' => $this->attempts(),
                    'max_attempts' => $this->tries,
                    'error' => $exception->getMessage(),
                    'exception' => $exception::class,
                    'failure_category' => $this->classifyFailure($exception->getMessage()),
                ],
            );

            throw $exception;
        }
    }

    protected function welcomeSmsEnabled(object $event): bool
    {
        if (method_exists($event, 'hasWelcomeSmsEnabled')) {
            return (bool) $event->hasWelcomeSmsEnabled();
        }

        return (bool) ($event->welcome_sms_enabled ?? false);
    }

    protected function welcomeSmsTemplate(object $event): string
    {
        return filled($event->welcome_sms_message ?? null)
            ? (string) $event->welcome_sms_message
            : 'Karibu #NAME# kwenye #EVENT_NAME#. Tunafurahi kuwa nawe. Furahia tukio hili maalum.';
    }

    protected function alreadySent(int $inviteeId): bool
    {
        if (! Schema::hasTable('sms_logs')) {
            return false;
        }

        return DB::table('sms_logs')
            ->where('invitee_id', $inviteeId)
            ->whereIn('sms_type', ['welcome_sms', 'welcome_checkin'])
            ->whereIn('status', ['logged', 'accepted', 'sent', 'delivered'])
            ->exists();
    }

    protected function recordSmsLog(
        Invitee $invitee,
        string $message,
        string $status,
        ?string $provider = null,
        ?string $providerMessageId = null,
        ?string $errorMessage = null,
        ?array $providerResponse = null,
    ): ?int {
        if (! Schema::hasTable('sms_logs')) {
            return null;
        }

        $now = now();
        $columns = Schema::getColumnListing('sms_logs');

        $row = [
            'event_id' => $invitee->event_id,
            'invitee_id' => $invitee->id,
            'phone' => $invitee->phone,
            'recipient' => $invitee->phone,
            'to' => $invitee->phone,
            'sms_type' => 'welcome_sms',
            'type' => 'welcome_sms',
            'message_type' => 'welcome_sms',
            'channel' => 'sms',
            'message' => $message,
            'body' => $message,
            'status' => $status,
            'provider' => $provider,
            'provider_name' => $provider,
            'provider_status' => $status,
            'provider_message_id' => $providerMessageId,
            'shoot_id' => $providerMessageId,
            'message_id' => $providerMessageId,
            'error_message' => $errorMessage,
            'error' => $errorMessage,
            'sent_at' => in_array($status, ['logged', 'accepted', 'sent', 'delivered'], true) ? $now : null,
            'delivered_at' => $status === 'delivered' ? $now : null,
            'failed_at' => in_array($status, ['failed', 'undelivered', 'expired', 'rejected'], true) ? $now : null,
            'provider_response' => $providerResponse ? json_encode($providerResponse) : null,
            'response' => $providerResponse ? json_encode($providerResponse) : null,
            'meta' => $providerResponse ? json_encode($providerResponse) : null,
            'send_source' => 'check_in',
            'sent_by_user_id' => null,
            'sent_by' => null,
            'user_id' => null,
            'batch_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $insertable = Arr::only($row, $columns);

        if ($insertable === []) {
            return null;
        }

        return (int) DB::table('sms_logs')->insertGetId($insertable);
    }

    protected function classifyFailure(string $message): string
    {
        $message = strtolower($message);

        return match (true) {
            str_contains($message, 'insufficient credit'),
            str_contains($message, 'insufficient balance') => 'insufficient_credit',

            str_contains($message, 'phone number'),
            str_contains($message, 'invalid phone') => 'invalid_phone',

            str_contains($message, 'timeout'),
            str_contains($message, 'timed out') => 'provider_timeout',

            str_contains($message, 'not configured'),
            str_contains($message, 'api key'),
            str_contains($message, 'api secret') => 'configuration_error',

            str_contains($message, 'rejected') => 'provider_rejected',

            default => 'unknown',
        };
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('SendWelcomeSmsJob permanently failed.', [
            'invitee_id' => $this->inviteeId,
            'error' => $exception?->getMessage(),
        ]);

        $invitee = Invitee::query()
            ->with('event')
            ->find($this->inviteeId);

        if (! $invitee) {
            AuditLogService::system(
                action: 'welcome_sms.permanently_failed',
                description: 'Welcome SMS job permanently failed, but the invitee record was unavailable.',
                metadata: [
                    'invitee_id' => $this->inviteeId,
                    'max_attempts' => $this->tries,
                    'error' => $exception?->getMessage(),
                    'exception' => $exception ? $exception::class : null,
                ],
            );

            return;
        }

        AuditLogService::record(
            action: 'welcome_sms.permanently_failed',
            subject: $invitee,
            eventId: $invitee->event_id,
            description: 'Welcome SMS job permanently failed after all queue attempts.',
            metadata: [
                'invitee_id' => $invitee->id,
                'invitee_name' => $invitee->name,
                'phone' => $invitee->phone,
                'max_attempts' => $this->tries,
                'error' => $exception?->getMessage(),
                'exception' => $exception ? $exception::class : null,
                'failure_category' => $this->classifyFailure((string) $exception?->getMessage()),
            ],
        );
    }
}
