<?php

namespace App\Services;

use Illuminate\Support\Facades\Schema;

class InvitationSmsReportStatusService
{
    public function expression(): string
    {
        $channel = 'sms';
        $latestMessage = Schema::hasTable('message_logs')
            ? "(SELECT ml.status FROM message_logs ml
                WHERE ml.event_id = invitees.event_id AND ml.invitee_id = invitees.id
                AND ml.channel = '".$channel."' AND ml.type = 'invitation'
                AND (invitees.invitation_phone_changed_at IS NULL OR ml.id > COALESCE(invitees.invitation_message_reset_log_id, 0))
                ORDER BY ml.id DESC LIMIT 1)" : 'NULL';
        $raw = $latestMessage;

        if ($channel === 'sms' && Schema::hasTable('sms_logs')) {
            $latestSms = "(SELECT sl.status FROM sms_logs sl
                WHERE sl.event_id = invitees.event_id AND sl.invitee_id = invitees.id
                AND sl.sms_type IN ('invitation', 'invitation_card')
                AND (invitees.invitation_phone_changed_at IS NULL OR sl.id > COALESCE(invitees.invitation_sms_reset_log_id, 0))
                ORDER BY sl.id DESC LIMIT 1)";
            $raw = "COALESCE({$latestSms}, {$latestMessage})";
        }

        {
            $raw = "CASE
                WHEN invitees.last_message_channel = 'sms'
                    AND invitees.last_message_status IN ('queued','pending','sending','processing')
                    AND invitees.sms_status = 'pending' THEN 'queued'
                WHEN {$raw} IS NOT NULL THEN {$raw}
                WHEN invitees.sms_status IN ('sent','delivered','failed') THEN 'unknown'
                ELSE 'not_sent' END";
        }

        $normalized = "LOWER(TRIM(COALESCE(({$raw}), 'not_sent')))";
        return "CASE
            WHEN {$normalized} = 'delivered' THEN 'delivered'
            WHEN {$normalized} = 'read' THEN 'read'
            WHEN {$normalized} IN ('failed', 'undelivered', 'expired', 'rejected') THEN 'failed'
            WHEN {$normalized} IN ('queued', 'pending', 'sending', 'processing') THEN 'queued'
            WHEN {$normalized} IN ('submitted', 'accepted', 'sent', 'success') THEN 'submitted'
            WHEN {$normalized} = 'logged' THEN 'logged_only'
            WHEN {$normalized} = 'not_sent' THEN 'not_sent'
            ELSE 'unknown' END";
    }

}
