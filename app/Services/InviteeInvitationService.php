<?php

namespace App\Services;

use App\Jobs\SendInvitationSmsJob;
use App\Jobs\SendInvitationWhatsAppJob;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\MessageTemplate;
use App\Support\EliveMessagePlaceholders;
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
            ->exists()) {
            return $this->result('skipped', 'This WhatsApp invitation was already submitted.');
        }

        $claimed = DB::table('whatsapp_template_sends')->insertOrIgnore([
            'event_id' => $event->id,
            'invitee_id' => $invitee->id,
            'message_template_id' => $template->id,
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
            );
        } catch (Throwable $exception) {
            DB::table('whatsapp_template_sends')
                ->where('event_id', $event->id)
                ->where('invitee_id', $invitee->id)
                ->where('message_template_id', $template->id)
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

    private function result(string $status, string $message): array
    {
        return ['status' => $status, 'message' => $message];
    }
}
