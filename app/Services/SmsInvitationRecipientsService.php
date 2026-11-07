<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Invitee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only eligibility logic shared by recipient preview and actual SMS dispatch.
 *
 * A WhatsApp provider submission is NOT proof of handset delivery, and a missing
 * delivery callback is NOT proof of failure.
 */
class SmsInvitationRecipientsService
{
    public const SCOPE_NOT_SENT = 'not_sent';
    public const SCOPE_FAILED_WHATSAPP = 'failed_whatsapp';
    public const SCOPE_WHATSAPP_UNCONFIRMED = 'whatsapp_unconfirmed';
    public const SCOPE_FAILED_SMS = 'failed_sms';
    public const SCOPE_ALL_ACTIVE = 'all_active';

    public static function scopes(): array
    {
        return [
            self::SCOPE_NOT_SENT => 'Not Yet Sent SMS',
            self::SCOPE_FAILED_WHATSAPP => 'Failed WhatsApp Invitations',
            self::SCOPE_WHATSAPP_UNCONFIRMED => 'WhatsApp Not Delivered (24h+)',
            self::SCOPE_FAILED_SMS => 'Failed SMS Invitations',
            self::SCOPE_ALL_ACTIVE => 'All Active Invitees',
        ];
    }

    public function candidateQuery(Event $event): \Illuminate\Database\Eloquent\Builder
    {
        return Invitee::query()
            ->where('event_id', $event->id)
            ->where('card_status', Invitee::CARD_STATUS_ACTIVE)
            ->whereNotNull('phone')->where('phone', '!=', '')
            ->whereNotNull('short_code')->where('short_code', '!=', '');
    }

    /**
     * Returns a reason whenever this invitee must not be sent an SMS.
     * Conservatively honours older status-only records even when logs are absent.
     */
    public function exclusionReason(Invitee $invitee): ?string
    {
        if (in_array((string) $invitee->invitation_sms_status, ['sent', 'delivered', 'accepted', 'submitted', 'logged', 'queued'], true)) {
            return 'Invitation SMS already queued, submitted or delivered.';
        }

        if (in_array((string) $invitee->sms_status, ['sent', 'delivered', 'accepted', 'submitted', 'logged'], true)) {
            return 'SMS previously submitted; verify its history before resending.';
        }

        if ($invitee->last_message_channel === 'sms' && $invitee->last_message_status === 'queued') {
            return 'An SMS is already queued.';
        }

        if (Schema::hasTable('sms_logs') && DB::table('sms_logs')
            ->where('event_id', $invitee->event_id)->where('invitee_id', $invitee->id)
            ->whereIn('sms_type', ['invitation', 'invitation_card'])
            ->whereIn('status', ['pending', 'queued', 'sending', 'sent', 'accepted', 'submitted', 'logged', 'delivered'])
            ->exists()) {
            return 'An invitation SMS is already in progress or accepted by the provider.';
        }

        if (Schema::hasTable('message_logs') && DB::table('message_logs')
            ->where('event_id', $invitee->event_id)->where('invitee_id', $invitee->id)
            ->where('channel', 'sms')->where('type', 'invitation')
            ->whereIn('status', ['pending', 'queued', 'submitted', 'accepted', 'sent', 'logged', 'delivered'])
            ->exists()) {
            return 'An invitation SMS has already been logged or submitted.';
        }

        return null;
    }

    public function eligible(Invitee $invitee, string $scope): bool
    {
        if (! array_key_exists($scope, self::scopes())
            || $invitee->card_status !== Invitee::CARD_STATUS_ACTIVE
            || blank($invitee->phone) || blank($invitee->short_code)
            || $this->exclusionReason($invitee) !== null) {
            return false;
        }

        return match ($scope) {
            self::SCOPE_FAILED_SMS => $this->smsFailed($invitee),
            self::SCOPE_FAILED_WHATSAPP => $this->whatsappCondition($invitee, true),
            self::SCOPE_WHATSAPP_UNCONFIRMED => $this->whatsappCondition($invitee, false),
            default => true,
        };
    }

    private function smsFailed(Invitee $invitee): bool
    {
        if (in_array((string) $invitee->invitation_sms_status, ['failed', 'undelivered', 'expired', 'rejected'], true)) {
            return true;
        }

        return Schema::hasTable('sms_logs') && DB::table('sms_logs')
            ->where('event_id', $invitee->event_id)->where('invitee_id', $invitee->id)
            ->whereIn('sms_type', ['invitation', 'invitation_card'])
            ->whereIn('status', ['failed', 'undelivered', 'expired', 'rejected'])
            ->exists();
    }

    private function whatsappCondition(Invitee $invitee, bool $failed): bool
    {
        // A delivered/read invitation on either the current or historical template
        // must never be targeted as a WhatsApp failure/fallback.
        if (in_array((string) $invitee->whatsapp_status, ['delivered', 'read'], true)) {
            return false;
        }

        if (Schema::hasTable('message_logs') && DB::table('message_logs')
            ->where('event_id', $invitee->event_id)->where('invitee_id', $invitee->id)
            ->where('channel', 'whatsapp')->where('type', 'invitation')
            ->whereIn('status', ['delivered', 'read'])->exists()) {
            return false;
        }

        if (! Schema::hasTable('whatsapp_template_sends')) {
            return false;
        }

        $latest = DB::table('whatsapp_template_sends')
            ->where('event_id', $invitee->event_id)->where('invitee_id', $invitee->id)
            ->orderByDesc('id')->first(['status', 'updated_at']);

        if (! $latest) {
            return false;
        }

        $status = strtolower((string) $latest->status);

        if ($failed) {
            return $status === 'failed';
        }

        // Do not classify an in-flight or recently accepted WhatsApp as failed.
        // This option requires explicit manual confirmation at send time.
        return in_array($status, ['submitted', 'accepted', 'sent'], true)
            && $latest->updated_at !== null
            && \Illuminate\Support\Carbon::parse($latest->updated_at)->lte(now()->subDay());
    }

    public function counts(Event $event, string $scope): array
    {
        if (! array_key_exists($scope, self::scopes())) {
            return ['eligible' => 0, 'excluded' => 0];
        }

        $eligible = 0;
        $excluded = 0;

        $this->candidateQuery($event)->chunkById(200, function ($invitees) use ($scope, &$eligible, &$excluded): void {
            foreach ($invitees as $invitee) {
                if ($this->eligible($invitee, $scope)) {
                    $eligible++;
                } else {
                    $excluded++;
                }
            }
        });

        return compact('eligible', 'excluded');
    }
}
