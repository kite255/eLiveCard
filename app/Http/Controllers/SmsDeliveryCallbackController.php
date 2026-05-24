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
        $payload = $request->all();

        Log::info('SMS delivery callback received', [
            'payload' => $payload,
            'ip' => $request->ip(),
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
                'payload' => $payload,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'SMS log not found.',
            ], 404);
        }

        $status = $this->normalizeStatus($this->extractStatus($payload));

        $updateData = [
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

            default => SmsLog::STATUS_SENT,
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