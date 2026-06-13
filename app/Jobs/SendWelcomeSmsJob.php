<?php

namespace App\Jobs;

use App\Models\Invitee;
use App\Services\SmsService;
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

        if (! $event->hasWelcomeSmsEnabled()) {
            Log::info('Welcome SMS skipped because it is disabled for the event.', [
                'invitee_id' => $invitee->id,
                'event_id' => $event->id,
            ]);

            return;
        }

        if (blank($invitee->phone)) {
            $this->recordSmsLog(
                invitee: $invitee,
                message: $event->renderWelcomeSms($invitee),
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

        $message = $event->renderWelcomeSms($invitee);

        $pendingLogId = $this->recordSmsLog(
            invitee: $invitee,
            message: $message,
            status: 'pending',
        );

        try {
            $result = $smsService->sendCustomMessage(
                invitee: $invitee,
                message: $message,
                type: 'welcome_checkin',
            );

            $successful = (bool) ($result['success'] ?? false);
            $status = (string) ($result['status'] ?? ($successful ? 'sent' : 'failed'));
            $messageId = $result['message_id'] ?? null;
            $provider = $result['provider'] ?? null;
            $error = $result['error'] ?? null;

            $this->updateSmsLog(
                logId: $pendingLogId,
                status: $status,
                provider: $provider,
                providerMessageId: $messageId,
                errorMessage: $error,
                providerResponse: $result,
            );

            if (! $successful) {
                throw new \RuntimeException(
                    filled($error) ? (string) $error : 'Welcome SMS provider returned a failed response.'
                );
            }

            Log::info('Welcome SMS processed successfully.', [
                'invitee_id' => $invitee->id,
                'event_id' => $event->id,
                'status' => $status,
                'message_id' => $messageId,
            ]);
        } catch (Throwable $exception) {
            $this->updateSmsLog(
                logId: $pendingLogId,
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

    protected function alreadySent(int $inviteeId): bool
    {
        if (! Schema::hasTable('sms_logs')) {
            return false;
        }

        return DB::table('sms_logs')
            ->where('invitee_id', $inviteeId)
            ->where('sms_type', 'welcome_checkin')
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
            'sms_type' => 'welcome_checkin',
            'message' => $message,
            'status' => $status,
            'provider' => $provider,
            'provider_message_id' => $providerMessageId,
            'error_message' => $errorMessage,
            'sent_at' => in_array($status, ['logged', 'accepted', 'sent', 'delivered'], true) ? $now : null,
            'delivered_at' => $status === 'delivered' ? $now : null,
            'failed_at' => $status === 'failed' ? $now : null,
            'provider_response' => $providerResponse ? json_encode($providerResponse) : null,
            'send_source' => 'check_in',
            'sent_by_user_id' => null,
            'batch_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $insertable = Arr::only($row, $columns);

        return (int) DB::table('sms_logs')->insertGetId($insertable);
    }

    protected function updateSmsLog(
        ?int $logId,
        string $status,
        ?string $provider = null,
        ?string $providerMessageId = null,
        ?string $errorMessage = null,
        ?array $providerResponse = null,
    ): void {
        if (! $logId || ! Schema::hasTable('sms_logs')) {
            return;
        }

        $now = now();
        $columns = Schema::getColumnListing('sms_logs');

        $updates = [
            'status' => $status,
            'provider' => $provider,
            'provider_message_id' => $providerMessageId,
            'error_message' => $errorMessage,
            'sent_at' => in_array($status, ['logged', 'accepted', 'sent', 'delivered'], true) ? $now : null,
            'delivered_at' => $status === 'delivered' ? $now : null,
            'failed_at' => $status === 'failed' ? $now : null,
            'provider_response' => $providerResponse ? json_encode($providerResponse) : null,
            'updated_at' => $now,
        ];

        DB::table('sms_logs')
            ->where('id', $logId)
            ->update(Arr::only($updates, $columns));
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('SendWelcomeSmsJob permanently failed.', [
            'invitee_id' => $this->inviteeId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
