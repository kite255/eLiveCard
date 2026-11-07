<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Invitee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SmsInvitationAudienceService
{
    public const NOT_SENT = 'not_sent';
    public const FAILED_WHATSAPP = 'failed_whatsapp';
    public const WHATSAPP_UNDELIVERED = 'whatsapp_undelivered';
    public const FAILED_SMS = 'failed_sms';
    public const ALL_ACTIVE = 'all_active';

    public static function options(): array
    {
        return [
            self::NOT_SENT => 'Not Yet Sent SMS',
            self::FAILED_WHATSAPP => 'Failed WhatsApp Invitations',
            self::WHATSAPP_UNDELIVERED => 'WhatsApp Not Delivered (24h+)',
            self::FAILED_SMS => 'Failed SMS Invitations',
            self::ALL_ACTIVE => 'All Active Invitees',
        ];
    }

    /**
     * This is only an eligibility snapshot: delivery may change between preview and queueing.
     * Always pass each selected record through InviteeInvitationService::send() again.
     *
     * @return array{eligible: Collection, total: int, excluded: int, selection: int}
     */
    public function preview(Event $event, string $scope): array
    {
        if (! array_key_exists($scope, self::options())) {
            throw new \InvalidArgumentException('Invalid SMS invitation recipient scope.');
        }

        $invitees = Invitee::query()
            ->where('event_id', $event->id)
            ->where('card_status', Invitee::CARD_STATUS_ACTIVE)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->whereNotNull('short_code')
            ->where('short_code', '!=', '')
            ->get();

        if ($invitees->isEmpty()) {
            return ['eligible' => collect(), 'total' => 0, 'excluded' => 0, 'selection' => 0];
        }

        $ids = $invitees->modelKeys();

        // Historical acceptance counts even without provider logs; unknown legacy "sent"
        // states must never be retried automatically.
        $smsLogs = Schema::hasTable('sms_logs')
            ? DB::table('sms_logs')->where('event_id', $event->id)
                ->whereIn('invitee_id', $ids)
                ->whereIn('sms_type', ['invitation', 'invitation_card'])
                ->get(['invitee_id', 'status'])->groupBy('invitee_id')
            : collect();
        $smsMessageLogs = Schema::hasTable('message_logs')
            ? DB::table('message_logs')->where('event_id', $event->id)
                ->whereIn('invitee_id', $ids)->where('channel', 'sms')
                ->whereIn('type', ['invitation', 'invitation_card'])
                ->get(['invitee_id', 'status'])->groupBy('invitee_id')
            : collect();

        $waTracking = Schema::hasTable('whatsapp_template_sends')
            ? DB::table('whatsapp_template_sends')
                ->where('event_id', $event->id)->whereIn('invitee_id', $ids)
                ->orderByDesc('id')->get(['invitee_id', 'status', 'updated_at'])
                ->groupBy('invitee_id')
            : collect();
        $waLogs = Schema::hasTable('message_logs')
            ? DB::table('message_logs')->where('event_id', $event->id)
                ->whereIn('invitee_id', $ids)->where('channel', 'whatsapp')
                ->where('type', 'invitation')
                ->orderByDesc('id')->get(['invitee_id', 'status', 'updated_at'])
                ->groupBy('invitee_id')
            : collect();

        $eligible = $invitees->filter(function (Invitee $invitee) use (
            $scope, $smsLogs, $smsMessageLogs, $waTracking, $waLogs
        ): bool {
            $sms = collect($smsLogs->get($invitee->id, collect()))
                ->concat($smsMessageLogs->get($invitee->id, collect()));
            $statuses = $sms->pluck('status')->map(fn ($s) => strtolower((string) $s));
            $accepted = ['sent', 'delivered', 'read', 'submitted', 'accepted', 'logged'];
            $inFlight = ['pending', 'queued', 'sending', 'processing'];
            $hasAccepted = $statuses->intersect($accepted)->isNotEmpty()
                || in_array((string) $invitee->invitation_sms_status, $accepted, true)
                || filled($invitee->invitation_sms_sent_at);
            $hasQueued = $statuses->intersect($inFlight)->isNotEmpty()
                || ($invitee->last_message_channel === 'sms'
                    && in_array((string) $invitee->last_message_status, $inFlight, true));
            if ($hasAccepted || $hasQueued) {
                return false;
            }

            $failedSms = $statuses->contains('failed')
                || $statuses->contains('rejected')
                || $statuses->contains('undelivered')
                || $invitee->invitation_sms_status === 'failed';
            if ($scope === self::NOT_SENT) {
                return ! $failedSms;
            }
            if ($scope === self::FAILED_SMS) {
                return $failedSms;
            }
            if ($scope === self::ALL_ACTIVE) {
                return true;
            }

            // Do not infer WhatsApp delivery from the shared last_message_status;
            // that can represent an RSVP reply or a different channel.
            $tracked = collect($waTracking->get($invitee->id, collect()))
                ->concat($waLogs->get($invitee->id, collect()));
            if ($tracked->isEmpty()) {
                return false;
            }
            $waStatuses = $tracked->pluck('status')->map(fn ($s) => strtolower((string) $s));
            if ($waStatuses->intersect(['delivered', 'read', 'replied'])->isNotEmpty()) {
                return false;
            }

            if ($scope === self::FAILED_WHATSAPP) {
                // A later accepted attempt must supersede an earlier failure.
                $latest = $tracked->sortByDesc('updated_at')->first();
                return in_array(strtolower((string) $latest->status), ['failed', 'undelivered', 'rejected'], true);
            }

            // Unconfirmed provider submission after a 24-hour waiting period.
            // Never treat queued/pending/failed as submitted, and never auto-send.
            $latest = $tracked->sortByDesc('updated_at')->first();
            return in_array(strtolower((string) $latest->status), ['submitted', 'accepted', 'sent'], true)
                && filled($latest->updated_at)
                && \Illuminate\Support\Carbon::parse($latest->updated_at)->lte(now()->subDay());
        })->values();

        return [
            'eligible' => $eligible,
            'total' => $eligible->count(),
            'excluded' => $invitees->count() - $eligible->count(),
            'selection' => $invitees->count(),
        ];
    }
}
