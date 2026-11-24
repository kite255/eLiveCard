<?php

namespace App\Http\Controllers;

use App\Models\Invitee;
use App\Models\SmsLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SmsDeliveryCallbackController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // Never accept unauthenticated delivery receipts. Configure the same
        // secret on the SMS provider and in SMS_DELIVERY_CALLBACK_TOKEN.
        $secret = (string) config('services.sms.delivery_callback_token', '');
        $supplied = (string) $request->header('X-SMS-Callback-Token', '');

        if ($secret === '' || ! hash_equals($secret, $supplied)) {
            Log::warning('SMS delivery callback rejected: invalid or missing authentication.');

            return response()->json(['success' => false, 'message' => 'Unauthorized callback.'], 401);
        }

        $payload = $request->all();

        Log::info('SMS delivery callback received.', [
            'provider_message_id_present' => filled($this->extractProviderMessageId($payload)),
        ]);

        $providerMessageId = $this->extractProviderMessageId($payload);

        if (! $providerMessageId) {
            return response()->json([
                'success' => false,
                'message' => 'Provider message ID is missing.',
            ], 422);
        }

        $smsLog = SmsLog::query()
            ->where('provider_message_id', $providerMessageId)
            ->latest()
            ->first();

        if (! $smsLog) {
            Log::warning('SMS delivery callback received for unknown message ID', [
                'provider_message_id' => $providerMessageId,
                'provider_message_id' => $providerMessageId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'SMS log not found.',
            ], 404);
        }

        $rawStatus = $this->extractStatus($payload);
        $status = $this->normalizeStatus($rawStatus);

        if ($status === SmsLog::STATUS_UNKNOWN) {
            Log::warning('SMS callback contains an unrecognized delivery status.', [
                'provider_message_id' => $providerMessageId,
            ]);

            return response()->json(['success' => false, 'message' => 'Unsupported delivery status.'], 422);
        }

        // A callback may arrive late or be delivered more than once.
        // Never downgrade an already terminal provider receipt.
        if (in_array($smsLog->status, [
            SmsLog::STATUS_DELIVERED, SmsLog::STATUS_FAILED,
            SmsLog::STATUS_UNDELIVERED, SmsLog::STATUS_REJECTED, SmsLog::STATUS_EXPIRED,
        ], true)) {
            return response()->json(['success' => true, 'message' => 'Final delivery status already recorded.']);
        }

        $updateData = [
            'provider_status' => (string) $rawStatus,
            'delivery_report_checked_at' => now(),
            'status' => $status,
            'provider_response' => array_merge(
                $smsLog->provider_response ?? [],
                [
                    'delivery_callback' => $payload,
                    'delivery_callback_received_at' => now()->toDateTimeString(),
                ]
            ),
        ];

        if ($status === SmsLog::STATUS_DELIVERED) {
            $updateData['delivered_at'] = now();
            $updateData['error_message'] = null;
        }

        if ($status === SmsLog::STATUS_FAILED) {
            $updateData['failed_at'] = now();
            $updateData['error_message'] = $this->extractErrorMessage($payload);
        }

        // Update the original log, but not an invitee whose phone changed
        // or whose newer SMS send has superseded this receipt.
        $smsLog->update($updateData);

        if ($smsLog->invitee) {
            $this->updateInviteeSmsStatus($smsLog);
        }

        return response()->json([
            'success' => true,
            'message' => 'Delivery callback processed successfully.',
        ]);
    }

    protected function extractProviderMessageId(array $payload): ?string
    {
        return $payload['provider_message_id']
            ?? $payload['message_id']
            ?? $payload['messageId']
            ?? $payload['sms_id']
            ?? $payload['smsId']
            ?? $payload['shoot_id']
            ?? $payload['shootId']
            ?? $payload['id']
            ?? $payload['data']['message_id']
            ?? $payload['data']['messageId']
            ?? $payload['data']['shoot_id']
            ?? $payload['data']['shootId']
            ?? null;
    }

    protected function extractStatus(array $payload): ?string
    {
        return $payload['status']
            ?? $payload['delivery_status']
            ?? $payload['deliveryStatus']
            ?? $payload['state']
            ?? $payload['data']['status']
            ?? $payload['data']['delivery_status']
            ?? $payload['data']['deliveryStatus']
            ?? null;
    }

    protected function normalizeStatus(?string $status): string
    {
        $status = Str::lower(trim((string) $status));

        return match ($status) {
            'delivered',
            'delivery_success',
            'success',
            'successful',
            'ok' => SmsLog::STATUS_DELIVERED,

            'failed',
            'fail',
            'undelivered',
            'rejected',
            'expired',
            'error',
            'not_delivered' => SmsLog::STATUS_FAILED,

            'pending',
            'queued',
            'processing',
            'submitted',
            'sent' => SmsLog::STATUS_SENT,

            default => SmsLog::STATUS_UNKNOWN,
        };
    }

    protected function extractErrorMessage(array $payload): ?string
    {
        return $payload['error']
            ?? $payload['error_message']
            ?? $payload['message']
            ?? $payload['reason']
            ?? $payload['data']['error']
            ?? $payload['data']['error_message']
            ?? $payload['data']['message']
            ?? $payload['data']['reason']
            ?? 'SMS delivery failed.';
    }

    protected function updateInviteeSmsStatus(SmsLog $smsLog): void
    {
        $invitee = $smsLog->invitee;

        if (! $invitee instanceof Invitee) {
            return;
        }

        // Do not overwrite an invitee's state using an older receipt, an
        // earlier phone generation or a different kind of SMS message.
        $inviteeLatestLogId = SmsLog::query()
            ->where('invitee_id', $invitee->id)
            ->max('id');

        if ((int) $inviteeLatestLogId !== (int) $smsLog->id) {
            return;
        }

        if ($invitee->invitation_phone_changed_at
            && in_array($smsLog->sms_type, [SmsLog::TYPE_INVITATION, SmsLog::TYPE_INVITATION_CARD], true)
            && $smsLog->id <= (int) $invitee->invitation_sms_reset_log_id) {
            return;
        }

        $status = match ($smsLog->status) {
            SmsLog::STATUS_DELIVERED => Invitee::SMS_STATUS_DELIVERED,
            SmsLog::STATUS_FAILED => Invitee::SMS_STATUS_FAILED,
            default => Invitee::SMS_STATUS_SENT,
        };

        $invitee->updateSmsStatusByType(
            smsType: $smsLog->sms_type,
            status: $status,
            messageId: $smsLog->provider_message_id,
            error: $smsLog->error_message
        );
    }
}