<?php

namespace App\Services;

use App\Models\Invitee;
use App\Models\MessageLog;
use App\Support\WhatsAppPhone;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class InviteeWhatsAppReplyService
{
    /**
     * Send a plain-text answer to a recent inbound WhatsApp message.
     *
     * This intentionally does not touch invitation status fields: a personal
     * reply is not a new invitation and must not alter delivery statistics.
     */
    public function send(Invitee $invitee, string $message, int $adminId): MessageLog
    {
        $invitee->refresh();
        $message = trim($message);

        if ($message === '' || mb_strlen($message) > 1000) {
            throw ValidationException::withMessages([
                'reply' => 'Enter a reply of 1 to 1,000 characters.',
            ]);
        }

        // A free-form WhatsApp reply is only allowed inside the 24-hour window
        // opened by the most recent inbound customer message.
        if (
            blank($invitee->last_reply_message)
            || ! $invitee->last_reply_at
            || $invitee->last_reply_at->isFuture()
            || $invitee->last_reply_at->lte(now()->subHours(24))
        ) {
            throw ValidationException::withMessages([
                'reply' => 'The WhatsApp 24-hour reply window has closed. Use an approved WhatsApp template instead.',
            ]);
        }

        $phone = WhatsAppPhone::normalize($invitee->phone);

        if (! $phone) {
            throw ValidationException::withMessages(['reply' => 'The invitee has no valid WhatsApp number.']);
        }

        $token = config('services.whatsapp.access_token');
        $numberId = config('services.whatsapp.phone_number_id');

        if (blank($token) || blank($numberId)) {
            throw ValidationException::withMessages(['reply' => 'WhatsApp Cloud API is not configured.']);
        }

        $log = MessageLog::query()->create([
            'event_id' => $invitee->event_id,
            'invitee_id' => $invitee->id,
            'channel' => 'whatsapp',
            'type' => 'custom',
            'phone' => $phone,
            'message' => $message,
            'status' => 'pending',
            'provider' => 'WhatsApp Cloud API',
            'meta' => ['purpose' => 'admin_reply', 'admin_id' => $adminId],
        ]);

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->timeout(30)
                ->post(
                    rtrim((string) config('services.whatsapp.base_url', 'https://graph.facebook.com'), '/')
                    .'/'.trim((string) config('services.whatsapp.api_version', 'v25.0'), '/')
                    .'/'.$numberId.'/messages',
                    [
                        'messaging_product' => 'whatsapp',
                        'to' => ltrim($phone, '+'),
                        'type' => 'text',
                        'text' => ['preview_url' => false, 'body' => $message],
                    ]
                );

            $messageId = data_get($response->json(), 'messages.0.id');

            if (! $response->successful() || blank($messageId)) {
                $reason = (string) (data_get($response->json(), 'error.message') ?: 'WhatsApp did not accept the reply.');
                $log->markAsFailed($reason);

                throw ValidationException::withMessages(['reply' => $reason]);
            }

            // Accepted by Meta is not the same as delivered/read.
            $log->markAsSent((string) $messageId);
            AuditLogService::record(
                action: 'invitee.whatsapp_reply_sent',
                subject: $invitee,
                eventId: (int) $invitee->event_id,
                description: 'Admin replied to an inbound WhatsApp message.',
                metadata: ['message_log_id' => $log->id, 'provider_message_id' => $messageId],
                userId: $adminId,
            );

            return $log;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $log->markAsFailed('WhatsApp send request failed.');

            report($e);
            throw ValidationException::withMessages([
                'reply' => 'Unable to send the WhatsApp reply. Please try again.',
            ]);
        }
    }
}
