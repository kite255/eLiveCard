<?php

namespace App\Jobs;

use App\Models\Invitee;
use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

/**
 * Send one event SMS outside the Livewire request.
 *
 * A provider timeout is ambiguous: the provider may already have accepted the
 * message. Do not automatically retry these sends or risk duplicate charges.
 * Failed sends are recorded by SmsService and can be reviewed before retry.
 */
class SendEventSmsJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public int $uniqueFor = 3600;

    public function __construct(
        public int $eventId,
        public int $inviteeId,
        public string $message,
        public string $smsType,
    ) {
        // Keep the existing communications worker compatible by default.
        $this->onQueue((string) config('messaging.sms_queue', 'communications'));
    }

    public function uniqueId(): string
    {
        return $this->eventId.'-'.$this->inviteeId.'-'.$this->smsType;
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('event-sms-'.$this->uniqueId()))
                ->releaseAfter(10)
                ->expireAfter(180),
        ];
    }

    public function handle(SmsService $sms): void
    {
        $invitee = Invitee::query()
            ->with(['event', 'cardType'])
            ->where('event_id', $this->eventId)
            ->find($this->inviteeId);

        if (! $invitee || blank($invitee->phone)) {
            return;
        }

        // Re-check eligibility at execution time, not only at queue time.
        if ($this->smsType === 'rsvp_pending_reminder'
            && ! in_array($invitee->rsvp_status, [null, '', 'pending'], true)) {
            return;
        }

        if ($this->smsType === 'event_day_reminder'
            && $invitee->rsvp_status !== 'attending') {
            return;
        }

        if ($this->smsType === 'thank_you_sms'
            && ! ($invitee->checked_in_at || $invitee->checked_in_count > 0)) {
            return;
        }

        if ($invitee->smsLogs()
            ->where('event_id', $this->eventId)
            ->where('sms_type', $this->smsType)
            ->whereIn('status', ['logged', 'accepted', 'sent', 'delivered'])
            ->exists()) {
            return;
        }

        // SmsService handles per-recipient placeholders, provider calls and logs.
        $result = $sms->sendCustomMessage($invitee, $this->message, $this->smsType);

        if (! ($result['success'] ?? false)) {
            Log::warning('Queued event SMS failed; requires reviewed manual retry.', [
                'event_id' => $this->eventId,
                'invitee_id' => $this->inviteeId,
                'sms_type' => $this->smsType,
                'error' => $result['error'] ?? null,
            ]);
        }
    }
}
