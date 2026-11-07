<?php

namespace App\Services;

use App\Models\Invitee;
use App\Models\SmsLog;
use Illuminate\Support\Facades\Schema;

class InvitationSmsDeliveryStatusService
{
    /**
     * Historical invitee statuses are not delivery evidence. Prefer the latest
     * invitation SMS provider log; otherwise expose the uncertainty explicitly.
     *
     * @return array{label: string, color: string, description: string, log: ?SmsLog}
     */
    public function forInvitee(Invitee $invitee): array
    {
        $log = Schema::hasTable('sms_logs')
            ? SmsLog::query()->where('event_id', $invitee->event_id)
                ->where('invitee_id', $invitee->id)
                ->whereIn('sms_type', [SmsLog::TYPE_INVITATION, SmsLog::TYPE_INVITATION_CARD])
                ->latest('id')->first()
            : null;

        if ($invitee->last_message_channel === 'sms'
            && in_array((string) $invitee->last_message_status, ['queued', 'pending', 'sending', 'processing'], true)
            && (string) $invitee->sms_status === Invitee::SMS_STATUS_PENDING) {
            return $this->result('Queued', 'warning', 'Invitation is awaiting queue processing. Delivery is not confirmed.', $log);
        }

        if (! $log) {
            return $this->result('Unverified', 'gray',
                'No invitation SMS delivery log found. Stored invitee status alone cannot confirm gateway submission or delivery.');
        }

        $status = strtolower(trim((string) $log->status));
        $provider = trim((string) $log->provider_status);
        $reference = trim((string) $log->provider_message_id);
        $checked = $log->delivery_report_checked_at?->format('d M Y H:i');

        $label = match ($status) {
            'delivered' => 'Delivered',
            'failed', 'rejected', 'undelivered', 'expired' => 'Failed',
            'queued', 'pending' => 'Queued',
            'sending', 'processing' => 'Processing',
            'sent', 'submitted', 'accepted' => 'Submitted',
            'logged' => 'Logged Only',
            default => 'Unknown',
        };

        $color = match ($label) {
            'Delivered' => 'success',
            'Failed' => 'danger',
            'Queued', 'Processing' => 'warning',
            'Submitted' => 'info',
            default => 'gray',
        };

        $details = [
            'Stored status: '.($status ?: 'unknown'),
            'Provider status: '.($provider ?: 'not available'),
            'Provider reference: '.($reference ?: 'not available'),
            'Last delivery check: '.($checked ?: 'not checked'),
        ];

        if ($label === 'Submitted') {
            $details[] = 'Accepted/submitted does not confirm handset delivery.';
        }

        if ($log->error_message) {
            $details[] = 'Last error: '.$log->error_message;
        }

        return $this->result($label, $color, implode(' | ', $details), $log);
    }

    private function result(string $label, string $color, string $description, ?SmsLog $log = null): array
    {
        return compact('label', 'color', 'description', 'log');
    }
}
