<?php

namespace App\Jobs;

use App\Models\Invitee;
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

    public int $backoff = 15;

    public function __construct(
        public int $inviteeId
    ) {
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('welcome-sms-invitee-' . $this->inviteeId))
                ->releaseAfter(10)
                ->expireAfter(120),
        ];
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

            return;
        }

        $event = $invitee->event;

        if (! $event) {
            Log::warning('Welcome SMS skipped because event was not found.', [
                'invitee_id' => $invitee->id,
                'event_id' => $invitee->event_id,
            ]);

            return;
        }

        if (! $this->welcomeSmsEnabled($event)) {
            Log::info('Welcome SMS skipped because it is disabled for the event.', [
                'invitee_id' => $invitee->id,
                'event_id' => $event->id,
            ]);

            return;
        }

        $templateMessage = $this->welcomeSmsTemplate($event);
        $message = EliveMessagePlaceholders::render($templateMessage, $invitee);

        if (blank($invitee->phone)) {
            $this->recordSmsLog(
                invitee: $invitee,
                message: $message,
                status: 'failed',
                errorMessage: 'Invitee phone number is empty.',
            );

            Log::warning('Welcome SMS skipped because invitee has no phone number.', [
                'invitee_id' => $invitee->id,
                'event_id' => $event->id,
            ]);

            return;
        }

        if ($this->alreadySent($invitee->id)) {
            Log::info('Duplicate welcome SMS prevented.', [
                'invitee_id' => $invitee->id,
                'event_id' => $event->id,
            ]);

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
        } catch (Throwable $exception) {
            $this->recordSmsLog(
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

    public function failed(?Throwable $exception): void
    {
        Log::error('SendWelcomeSmsJob permanently failed.', [
            'invitee_id' => $this->inviteeId,
            'error' => $exception?->getMessage(),
        ]);
    }
}