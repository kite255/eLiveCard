<?php

namespace App\Services;

use App\Jobs\SendInvitationSmsJob;
use App\Jobs\SendInvitationWhatsAppJob;
use App\Services\AuditLogService;
use App\Services\SmsService;
use App\Services\WhatsAppApiCloudService;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\MessageTemplate;
use App\Support\EliveMessagePlaceholders;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class InviteeInvitationService
{
    public function template(Event $event, string $channel): ?MessageTemplate
    {
        if ($channel === MessageTemplate::CHANNEL_WHATSAPP) {
            $templateId = (int) ($event->fresh()?->default_whatsapp_template_id ?? 0);

            return $templateId > 0
                ? MessageTemplate::query()
                    ->whereKey($templateId)
                    ->where('channel', MessageTemplate::CHANNEL_WHATSAPP)
                    ->where('type', MessageTemplate::TYPE_INVITATION)
                    ->where('status', MessageTemplate::STATUS_ACTIVE)
                    ->whereNotNull('whatsapp_template_name')
                    ->where('whatsapp_template_name', '!=', '')
                    ->where(function ($query) use ($event): void {
                        $query->where('event_id', $event->id)->orWhereNull('event_id');
                    })
                    ->first()
                : null;
        }

        if ($channel !== MessageTemplate::CHANNEL_SMS) {
            return null;
        }

        return MessageTemplate::activeSmsTemplate((int) $event->id, MessageTemplate::TYPE_INVITATION);
    }

    public function preview(Invitee $invitee, string $channel): string
    {
        $invitee->loadMissing(['event', 'cardType']);
        $template = $invitee->event ? $this->template($invitee->event, $channel) : null;

        if (! $template) {
            return $channel === 'whatsapp'
                ? 'No active default WhatsApp invitation template is configured in Event Settings.'
                : 'No active SMS invitation template was found in Message Templates.';
        }

        if ($channel === 'whatsapp') {
            return 'Approved Meta template: '.$template->whatsapp_template_name
                .' ('.strtoupper((string) ($template->whatsapp_language_code ?: 'en')).'). '
                .'The actual WhatsApp content is defined by the approved Meta template.';
        }

        return EliveMessagePlaceholders::render((string) $template->content, $invitee);
    }

    public function send(Event $event, Invitee $invitee, string $channel): array
    {
        if ((int) $invitee->event_id !== (int) $event->id) {
            return $this->result('failed', 'Invitee does not belong to this event.');
        }

        if ($invitee->card_status !== Invitee::CARD_STATUS_ACTIVE) {
            return $this->result('skipped', 'Only active invitation cards can be sent.');
        }

        if (blank($invitee->short_code) || blank($invitee->phone)) {
            return $this->result('failed', 'A valid phone number and private invitation link are required.');
        }

        $template = $this->template($event, $channel);
        if (! $template) {
            return $this->result('failed', $channel === 'whatsapp'
                ? 'Select an active invitation WhatsApp template in Event Settings.'
                : 'Create or activate the SMS Invitation template in Message Templates.');
        }

        $invitee->loadMissing(['event', 'cardType']);

        if ($channel === MessageTemplate::CHANNEL_SMS) {
            if (! app(SmsService::class)->isTanzanianMobileNumber($invitee->phone)) {
                return $this->result('skipped', 'SMS is Tanzania-only. Use WhatsApp for this international contact.');
            }

            $exclusion = app(SmsInvitationRecipientsService::class)->exclusionReason($invitee);
            if ($exclusion !== null) {
                return $this->result('skipped', $exclusion);
            }

            $message = EliveMessagePlaceholders::render((string) $template->content, $invitee);

            if (trim($message) === '' || preg_match('/#[A-Z_]+#/', $message)) {
                return $this->result('failed', 'SMS template contains an empty message or unresolved placeholder.');
            }

            SendInvitationSmsJob::dispatch(
                eventId: (int) $event->id,
                inviteeId: (int) $invitee->id,
                customMessage: $message,
            );

            $invitee->forceFill([
                'last_message_channel' => 'sms',
                'last_message_status' => 'queued',
                'message_status' => 'queued',
                'sms_status' => Invitee::SMS_STATUS_PENDING,
            ])->save();

            return $this->result('queued', 'Invitation SMS queued for delivery. This does not mean it was delivered.');
        }

        if ($channel !== MessageTemplate::CHANNEL_WHATSAPP || ! Schema::hasTable('whatsapp_template_sends')) {
            return $this->result('failed', 'WhatsApp invitation tracking is unavailable. Run migrations first.');
        }

        // Do not duplicate existing accepted legacy WhatsApp invitations.
        if ($invitee->messageLogs()
            ->where('event_id', $event->id)
            ->where('channel', 'whatsapp')
            ->where('type', MessageTemplate::TYPE_INVITATION)
            ->whereIn('status', ['submitted', 'accepted', 'sent', 'delivered', 'read'])
            ->when($invitee->invitation_phone_changed_at, fn ($query) =>
                $query->where('id', '>', (int) $invitee->invitation_message_reset_log_id))
            ->exists()) {
            return $this->result('skipped', 'This WhatsApp invitation was already submitted.');
        }

        $claimed = DB::table('whatsapp_template_sends')->insertOrIgnore([
            'event_id' => $event->id,
            'invitee_id' => $invitee->id,
            'message_template_id' => $template->id,
            'phone_generation' => (int) $invitee->invitation_phone_generation,
            'status' => 'pending',
            'attempts' => 1,
            'last_attempt_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($claimed !== 1) {
            return $this->result('skipped', 'WhatsApp invitation already queued, submitted or failed. Use Retry Failed WhatsApp for failed attempts.');
        }

        try {
            SendInvitationWhatsAppJob::dispatch(
                inviteeId: (int) $invitee->id,
                languageCode: (string) ($template->whatsapp_language_code ?: 'en'),
                messageTemplateId: (int) $template->id,
                phoneGeneration: (int) $invitee->invitation_phone_generation,
            );
        } catch (Throwable $exception) {
            DB::table('whatsapp_template_sends')
                ->where('event_id', $event->id)
                ->where('invitee_id', $invitee->id)
                ->where('message_template_id', $template->id)
                ->where('phone_generation', (int) $invitee->invitation_phone_generation)
                ->update(['status' => 'failed', 'last_error' => mb_substr($exception->getMessage(), 0, 1000), 'updated_at' => now()]);

            return $this->result('failed', 'Could not queue WhatsApp invitation.');
        }

        $invitee->forceFill([
            'last_message_channel' => 'whatsapp',
            'last_message_status' => 'queued',
            'message_status' => 'queued',
            'whatsapp_status' => 'queued',
        ])->save();

        return $this->result('queued', 'WhatsApp invitation queued for delivery. This does not mean it was delivered.');
    }

    /**
     * Single-recipient send action: wait for gateway acceptance/rejection in the
     * current HTTP request. Bulk and selected-recipient actions still use queues.
     *
     * A provider submission is not a delivery receipt.
     */
    public function sendImmediately(Event $event, Invitee $invitee, string $channel, ?string $resendReason = null): array
    {
        if ($resendReason !== null) {
            $resendReason = trim($resendReason);
            if (mb_strlen($resendReason) < 10 || mb_strlen($resendReason) > 500) {
                return $this->result('failed', 'Provide a reason between 10 and 500 characters.');
            }
        }

        if ((int) $invitee->event_id !== (int) $event->id
            || $invitee->card_status !== Invitee::CARD_STATUS_ACTIVE) {
            return $this->result('skipped', 'The invitee must have an active invitation for this event.');
        }

        if (blank($invitee->phone) || blank($invitee->short_code)) {
            return $this->result('failed', 'A phone number and private invitation link are required.');
        }

        $template = $this->template($event, $channel);
        if (! $template) {
            return $this->result('failed', 'No active invitation template is configured for this channel.');
        }

        try {
            return Cache::lock('direct-invitation-'.$channel.'-'.$event->id.'-'.$invitee->id, 90)
                ->block(1, function () use ($event, $invitee, $template, $channel, $resendReason): array {
                    $invitee->refresh()->loadMissing(['event', 'cardType']);

                    if ((int) $invitee->event_id !== (int) $event->id || $invitee->card_status !== Invitee::CARD_STATUS_ACTIVE) {
                        return $this->result('skipped', 'Invitation card is no longer active.');
                    }

                    if (blank($invitee->phone) || blank($invitee->short_code)) {
                        return $this->result('failed', 'A phone number and private invitation link are required.');
                    }

                    if ($channel === MessageTemplate::CHANNEL_SMS) {
                        $sms = app(SmsService::class);
                        if (! $sms->isTanzanianMobileNumber($invitee->phone)) {
                            return $this->result('skipped', 'SMS is Tanzania-only. Use WhatsApp for this international contact.');
                        }
                        if ($resendReason === null) {
                            $exclusion = app(SmsInvitationRecipientsService::class)->exclusionReason($invitee);
                            if ($exclusion !== null) {
                                return $this->result('skipped', $exclusion);
                            }
                        } else {
                            if ($this->smsInvitationInFlight($invitee)) {
                                return $this->result('skipped', 'An invitation SMS is already queued or processing.');
                            }

                            try {
                                $sms->formatPhone($invitee->phone);
                            } catch (\Exception) {
                                return $this->result('failed', 'SMS invitations require a valid Tanzanian mobile number.');
                            }
                        }

                        $message = EliveMessagePlaceholders::render((string) $template->content, $invitee);
                        if (trim($message) === '' || preg_match('/#[A-Z_]+#/', $message)) {
                            return $this->result('failed', 'SMS template has an empty message or unresolved placeholders.');
                        }

                        if ($resendReason !== null) {
                            $this->recordResend($invitee, $channel, $resendReason);
                        }

                        // Set in-flight state before contacting the provider to block repeated clicks.
                        $invitee->forceFill([
                            'last_message_channel' => 'sms',
                            'last_message_status' => 'sending',
                            'message_status' => 'sending',
                            'sms_status' => Invitee::SMS_STATUS_PENDING,
                        ])->save();

                        try {
                            $response = $sms->sendInvitation($invitee, $message);
                            if (! ($response['success'] ?? false)) {
                                return $this->result('failed', 'SMS provider did not accept the invitation.');
                            }

                            $driver = $response['driver'] ?? null;
                            if ($driver === 'log' || ($response['status'] ?? null) === 'logged') {
                                return $this->result('logged', 'SMS was logged only, not submitted to the SMS gateway.');
                            }

                            return $this->result('submitted', 'SMS accepted by the gateway. Handset delivery is not yet confirmed.');
                        } catch (Throwable $exception) {
                            report($exception);
                            return $this->result('failed', 'SMS submission could not be confirmed. Check the provider log before retrying.');
                        }
                    }

                    if ($channel !== MessageTemplate::CHANNEL_WHATSAPP
                        || ! Schema::hasTable('whatsapp_template_sends')) {
                        return $this->result('failed', 'WhatsApp invitation tracking is unavailable.');
                    }

                    if ($resendReason === null && $invitee->messageLogs()
                        ->where('event_id', $event->id)
                        ->where('channel', 'whatsapp')
                        ->where('type', MessageTemplate::TYPE_INVITATION)
                        ->whereIn('status', ['submitted', 'accepted', 'sent', 'delivered', 'read'])
                        ->when($invitee->invitation_phone_changed_at, fn ($query) =>
                            $query->where('id', '>', (int) $invitee->invitation_message_reset_log_id))
                        ->exists()) {
                        return $this->result('skipped', 'This WhatsApp invitation was already submitted.');
                    }

                    $claimKey = [
                        'event_id' => $event->id,
                        'invitee_id' => $invitee->id,
                        'message_template_id' => $template->id,
                        'phone_generation' => (int) $invitee->invitation_phone_generation,
                    ];
                    // A queued worker only processes pending claims.
                    $claimStatus = $resendReason === null ? 'pending' : 'sending';
                    $now = now();

                    if ($resendReason !== null && DB::table('whatsapp_template_sends as sends')
                        ->join('message_templates as templates', 'templates.id', '=', 'sends.message_template_id')
                        ->where('sends.invitee_id', $invitee->id)
                        ->where('sends.event_id', $event->id)
                        ->where('templates.type', MessageTemplate::TYPE_INVITATION)
                        ->where('sends.phone_generation', (int) $invitee->invitation_phone_generation)
                        ->whereIn('sends.status', ['pending', 'sending'])
                        ->exists()) {
                        return $this->result('skipped', 'A WhatsApp invitation is already queued or processing.');
                    }

                    $claimed = DB::table('whatsapp_template_sends')->insertOrIgnore($claimKey + [
                        'status' => $claimStatus,
                        'attempts' => 1,
                        'last_attempt_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    if ($claimed !== 1 && $resendReason !== null) {
                        $claimed = DB::table('whatsapp_template_sends')
                            ->where($claimKey)
                            ->whereIn('status', ['submitted', 'failed'])
                            ->update([
                                'status' => $claimStatus,
                                'attempts' => DB::raw('attempts + 1'),
                                'last_attempt_at' => $now,
                                'updated_at' => $now,
                            ]);
                    }

                    if ($claimed !== 1) {
                        return $this->result('skipped', 'WhatsApp invitation has already been requested. Check its status before retrying.');
                    }

                    if ($resendReason !== null) {
                        $this->recordResend($invitee, $channel, $resendReason);
                    }

                    try {
                        app(WhatsAppApiCloudService::class)->sendInvitation(
                            invitee: $invitee,
                            languageCode: (string) ($template->whatsapp_language_code ?: 'en'),
                            messageTemplateId: (int) $template->id,
                        );

                        DB::table('whatsapp_template_sends')
                            ->where($claimKey)
                            ->where('status', $claimStatus)
                            ->update(['status' => 'submitted', 'last_error' => null, 'updated_at' => now()]);

                        return $this->result('submitted', 'WhatsApp accepted the invitation. Delivery/read confirmation may arrive later.');
                    } catch (Throwable $exception) {
                        report($exception);

                        DB::table('whatsapp_template_sends')
                            ->where($claimKey)
                            ->where('status', $claimStatus)
                            ->update([
                                'status' => 'failed',
                                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
                                'updated_at' => now(),
                            ]);

                        return $this->result('failed', 'WhatsApp submission could not be confirmed. Check the message log before retrying.');
                    }
                });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $exception) {
            return $this->result('skipped', 'This invitation is already being sent. Check its status.');
        }
    }

    /**
     * Explicit, single-recipient administrative override. Never use for bulk sending.
     * Historical delivery status and communication logs remain intact.
     */
    public function forceResendSms(Event $event, Invitee $invitee, string $reason): array
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 500) {
            return $this->result('failed', 'Provide a reason between 10 and 500 characters.');
        }

        if ((int) $invitee->event_id !== (int) $event->id
            || $invitee->card_status !== Invitee::CARD_STATUS_ACTIVE) {
            return $this->result('failed', 'Invitee must belong to the event and have an active card.');
        }

        if (blank($invitee->phone) || blank($invitee->short_code)) {
            return $this->result('failed', 'A phone number and private invitation link are required.');
        }

        $template = $this->template($event, MessageTemplate::CHANNEL_SMS);
        if (! $template) {
            return $this->result('failed', 'No active SMS invitation template is configured.');
        }

        return Cache::lock('force-sms-invitation-'.$event->id.'-'.$invitee->id, 30)
            ->block(3, function () use ($event, $invitee, $template, $reason): array {
                $invitee->refresh()->loadMissing(['event', 'cardType']);

                if ($invitee->card_status !== Invitee::CARD_STATUS_ACTIVE) {
                    return $this->result('skipped', 'Invitation card is no longer active.');
                }

                if (! app(SmsService::class)->isTanzanianMobileNumber($invitee->phone)) {
                    return $this->result('skipped', 'SMS is Tanzania-only. Use WhatsApp for this international contact.');
                }

                // A force resend must not bypass an in-flight invitation.
                if ($invitee->last_message_channel === 'sms'
                    && in_array((string) $invitee->last_message_status, ['queued', 'pending', 'sending', 'processing'], true)) {
                    return $this->result('skipped', 'An SMS is already queued or processing. Resolve it before forcing a resend.');
                }

                $message = EliveMessagePlaceholders::render((string) $template->content, $invitee);
                if (trim($message) === '' || preg_match('/#[A-Z_]+#/', $message)) {
                    return $this->result('failed', 'SMS template contains an empty message or unresolved placeholder.');
                }

                // Record authorization and intent before dispatch so a worker never
                // receives an untracked forced-send request.
                AuditLogService::record(
                    action: 'invitation_sms.force_resend_requested',
                    subject: $invitee,
                    eventId: $event->id,
                    description: 'An authorized user requested an explicit invitation SMS resend.',
                    metadata: [
                        'reason' => $reason,
                        'previous_invitation_sms_status' => $invitee->invitation_sms_status,
                        'previous_sms_status' => $invitee->sms_status,
                        'recipient_id' => $invitee->id,
                    ],
                );

                try {
                    SendInvitationSmsJob::dispatch(
                        eventId: (int) $event->id,
                        inviteeId: (int) $invitee->id,
                        customMessage: $message,
                        forceResend: true,
                    );

                    $invitee->forceFill([
                        'last_message_channel' => 'sms',
                        'last_message_status' => 'queued',
                        'message_status' => 'queued',
                        'sms_status' => Invitee::SMS_STATUS_PENDING,
                    ])->save();
                } catch (Throwable $error) {
                    report($error);

                    return $this->result('failed', 'Could not queue the forced invitation SMS.');
                }

                return $this->result('queued', 'Force resend queued. This may incur another SMS charge and does not confirm delivery.');
            });
    }

    private function smsInvitationInFlight(Invitee $invitee): bool
    {
        $inFlight = ['queued', 'pending', 'sending', 'processing'];

        // Only the latest invitation receipt for the current phone can block
        // another send. Historical pending logs may remain after a later SMS
        // was successfully submitted or delivered.
        $latest = Schema::hasTable('sms_logs')
            ? DB::table('sms_logs')
                ->where('event_id', $invitee->event_id)
                ->where('invitee_id', $invitee->id)
                ->whereIn('sms_type', ['invitation', 'invitation_card'])
                ->when($invitee->invitation_phone_changed_at, fn ($query) =>
                    $query->where('id', '>', (int) $invitee->invitation_sms_reset_log_id))
                ->orderByDesc('id')
                ->first(['status'])
            : null;

        if ($latest !== null) {
            return in_array((string) $latest->status, $inFlight, true);
        }

        // The send may be queued before an SMS log is created; preserve this
        // protection if there is no provider receipt yet.
        return ($invitee->last_message_channel === 'sms'
                && in_array((string) $invitee->last_message_status, $inFlight, true))
            || in_array((string) $invitee->invitation_sms_status, ['queued', 'sending', 'processing'], true);
    }

    private function recordResend(Invitee $invitee, string $channel, string $reason): void
    {
        AuditLogService::record(
            action: 'invitation_'.$channel.'.force_resend_requested',
            subject: $invitee,
            eventId: $invitee->event_id,
            description: 'An authorized user requested an explicit invitation resend.',
            metadata: ['channel' => $channel, 'reason' => $reason, 'recipient_id' => $invitee->id],
        );
    }

    private function result(string $status, string $message): array
    {
        return ['status' => $status, 'message' => $message];
    }
}
