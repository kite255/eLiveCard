<?php

namespace App\Services;

use Illuminate\Support\Facades\Schema;

class InvitationWhatsAppDeliveryStatusService
{
    /** Current invitation evidence, independent of the duplicate-send claim. */
    public function expression(): string
    {
        $receipt = Schema::hasTable('message_logs')
            ? "(SELECT LOWER(TRIM(ml.status)) FROM message_logs ml
                WHERE ml.event_id = invitees.event_id AND ml.invitee_id = invitees.id
                AND ml.channel = 'whatsapp' AND ml.type = 'invitation'
                AND (invitees.invitation_phone_changed_at IS NULL OR ml.id > COALESCE(invitees.invitation_message_reset_log_id, 0))
                ORDER BY ml.id DESC LIMIT 1)" : 'NULL';
        $claim = Schema::hasTable('whatsapp_template_sends')
            ? "(SELECT wt.status FROM whatsapp_template_sends wt
                WHERE wt.event_id = invitees.event_id AND wt.invitee_id = invitees.id
                AND wt.phone_generation = invitees.invitation_phone_generation
                AND wt.message_template_id = (SELECT e.default_whatsapp_template_id FROM events e WHERE e.id = invitees.event_id)
                ORDER BY wt.id DESC LIMIT 1)" : 'NULL';

        $raw = "COALESCE({$receipt}, {$claim}, CASE WHEN invitees.whatsapp_status IN ('sent','submitted','delivered','read','failed','queued','sending','pending') THEN 'unknown' ELSE 'not_sent' END)";

        return "CASE LOWER(TRIM({$raw}))
            WHEN 'read' THEN 'read' WHEN 'delivered' THEN 'delivered'
            WHEN 'sent' THEN 'sent' WHEN 'submitted' THEN 'submitted'
            WHEN 'accepted' THEN 'submitted' WHEN 'success' THEN 'submitted'
            WHEN 'failed' THEN 'failed' WHEN 'undelivered' THEN 'failed'
            WHEN 'expired' THEN 'failed' WHEN 'rejected' THEN 'failed'
            WHEN 'queued' THEN 'queued' WHEN 'pending' THEN 'queued'
            WHEN 'sending' THEN 'queued' WHEN 'processing' THEN 'queued'
            WHEN 'logged' THEN 'logged_only' WHEN 'not_sent' THEN 'not_sent'
            ELSE 'unknown' END";
    }

    /** Timestamp from the same current invitation log, never a reminder. */
    public function timestampExpression(): string
    {
        if (! Schema::hasTable('message_logs')) {
            return 'NULL';
        }

        return "(SELECT CASE LOWER(TRIM(ml.status))
            WHEN 'read' THEN ml.read_at
            WHEN 'delivered' THEN ml.delivered_at
            WHEN 'failed' THEN ml.failed_at
            WHEN 'sent' THEN ml.sent_at
            WHEN 'submitted' THEN ml.sent_at
            WHEN 'accepted' THEN ml.sent_at
            ELSE NULL END
            FROM message_logs ml
            WHERE ml.event_id = invitees.event_id AND ml.invitee_id = invitees.id
            AND ml.channel = 'whatsapp' AND ml.type = 'invitation'
            AND (invitees.invitation_phone_changed_at IS NULL OR ml.id > COALESCE(invitees.invitation_message_reset_log_id, 0))
            ORDER BY ml.id DESC LIMIT 1)";
    }
}
