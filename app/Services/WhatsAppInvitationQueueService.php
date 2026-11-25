<?php

namespace App\Services;

use App\Jobs\SendInvitationWhatsAppJob;
use App\Models\Invitee;
use App\Models\MessageTemplate;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Atomically claim and queue a WhatsApp invitation exactly once.
 *
 * Does not send to Meta directly. Failed-only retries must first atomically
 * transition their existing failed claim to pending before calling queue().
 */
class WhatsAppInvitationQueueService
{
    public function queue(Invitee $invitee, MessageTemplate $template, bool $claimAlreadyAcquired = false): array
    {
        $claim = DB::table('whatsapp_template_sends')
            ->where('event_id', $invitee->event_id)
            ->where('invitee_id', $invitee->id)
            ->where('message_template_id', $template->id)
            ->where('phone_generation', (int) $invitee->invitation_phone_generation);

        $claimed = $claimAlreadyAcquired
            ? (clone $claim)->where('status', 'pending')->exists()
            : DB::table('whatsapp_template_sends')->insertOrIgnore([
                'event_id' => $invitee->event_id,
                'invitee_id' => $invitee->id,
                'message_template_id' => $template->id,
                'phone_generation' => (int) $invitee->invitation_phone_generation,
                'status' => 'pending',
                'attempts' => 1,
                'last_attempt_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]) === 1;

        if (! $claimed) {
            return [
                'status' => 'skipped',
                'type' => 'warning',
                'title' => 'WhatsApp invitation already requested',
                'body' => 'This invitation is already queued, submitted or failed. Check delivery status before retrying.',
            ];
        }

        try {
            SendInvitationWhatsAppJob::dispatch(
                inviteeId: $invitee->id,
                languageCode: filled($template->whatsapp_language_code)
                    ? (string) $template->whatsapp_language_code
                    : MessageTemplate::LANGUAGE_ENGLISH,
                messageTemplateId: $template->id,
                phoneGeneration: (int) $invitee->invitation_phone_generation,
            )->afterCommit();
        } catch (Throwable $error) {
            // This cannot detect jobs lost *after* successful dispatch; those
            // remain protected by their claim until manually reconciled.
            (clone $claim)->where('status', 'pending')->update([
                'status' => 'failed',
                'last_error' => mb_substr('Queue dispatch failed: '.$error->getMessage(), 0, 1000),
                'updated_at' => now(),
            ]);

            report($error);

            return [
                'status' => 'failed',
                'type' => 'danger',
                'title' => 'WhatsApp queue dispatch failed',
                'body' => 'Unable to queue this invitation. Check communications worker and queue logs.',
            ];
        }

        return [
            'status' => 'queued',
            'type' => 'success',
            'title' => 'WhatsApp invitation queued',
            'body' => 'The WhatsApp invitation was queued for background delivery.',
        ];
    }
}
