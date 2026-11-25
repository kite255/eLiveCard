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
        // A template-send claim is a duplicate-prevention lock, NOT proof of
        // provider delivery. If it remains pending for over 24 hours, surface
        // that uncertainty to operators without changing the claim or retrying.
        // Keep newer message_log receipts authoritative over the claim.
        $staleCutoff = now()->subDay()->toDateTimeString();
        $claim = Schema::hasTable('whatsapp_template_sends')
            ? "(SELECT CASE
                    WHEN LOWER(TRIM(wt.status)) IN ('pending', 'queued', 'sending', 'processing')
                        AND wt.updated_at < '{$staleCutoff}'
                    THEN 'unverified'
                    ELSE wt.status
                END FROM whatsapp_template_sends wt
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
            WHEN 'logged' THEN 'logged_only' WHEN 'unverified' THEN 'unverified' WHEN 'not_sent' THEN 'not_sent'
            ELSE 'unknown' END";
    }

    /** Timestamp from the same current invitation log, never a reminder. */
    public function timestampExpression(): string
    {
        if (! Schema::hasTable('message_logs')) {
            return 'NULL';
        }

        $columns = Schema::getColumnListing('message_logs');
        $cases = [];
        foreach (['read' => 'read_at', 'delivered' => 'delivered_at', 'failed' => 'failed_at', 'sent' => 'sent_at', 'submitted' => 'sent_at', 'accepted' => 'sent_at'] as $status => $field) {
            if (in_array($field, $columns, true)) {
                $cases[] = "WHEN '{$status}' THEN ml.{$field}";
            }
        }
        if ($cases === []) {
            return 'NULL';
        }

        return "(SELECT CASE LOWER(TRIM(ml.status)) ".implode(' ', $cases)." ELSE NULL END
            FROM message_logs ml
            WHERE ml.event_id = invitees.event_id AND ml.invitee_id = invitees.id
            AND ml.channel = 'whatsapp' AND ml.type = 'invitation'
            AND (invitees.invitation_phone_changed_at IS NULL OR ml.id > COALESCE(invitees.invitation_message_reset_log_id, 0))
            ORDER BY ml.id DESC LIMIT 1)";
    }
}
