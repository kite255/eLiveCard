<?php

namespace App\Services;

use App\Models\Invitee;
use App\Models\InviteeConversation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class InviteeConversationService
{
    public function saveIncomingWhatsAppReply(
        Invitee $invitee,
        string $message,
        ?string $fromPhone = null,
        ?string $providerMessageId = null,
        ?array $providerResponse = null
    ): InviteeConversation {
        $conversation = InviteeConversation::create([
            'event_id' => $invitee->event_id,
            'invitee_id' => $invitee->id,
            'sent_by' => null,
            'channel' => 'whatsapp',
            'direction' => 'incoming',
            'from_phone' => $fromPhone ?? $invitee->phone,
            'to_phone' => null,
            'message' => $message,
            'status' => 'received',
            'provider_message_id' => $providerMessageId,
            'provider_response' => $providerResponse,
            'received_at' => now(),
        ]);

        $invitee->update([
            'last_message_channel' => 'whatsapp',
            'last_message_status' => 'replied',
            'last_reply_message' => $message,
            'last_reply_at' => now(),
        ]);

        return $conversation;
    }

    public function saveOutgoingWhatsAppReply(
        Invitee $invitee,
        string $message,
        ?User $sender = null,
        ?string $providerMessageId = null,
        ?array $providerResponse = null,
        string $status = 'sent'
    ): InviteeConversation {
        $sender ??= Auth::user();

        $conversation = InviteeConversation::create([
            'event_id' => $invitee->event_id,
            'invitee_id' => $invitee->id,
            'sent_by' => $sender?->id,
            'channel' => 'whatsapp',
            'direction' => 'outgoing',
            'from_phone' => null,
            'to_phone' => $invitee->phone,
            'message' => $message,
            'status' => $status,
            'provider_message_id' => $providerMessageId,
            'provider_response' => $providerResponse,
            'sent_at' => now(),
        ]);

        $invitee->update([
            'last_whatsapp_sent_at' => now(),
            'last_message_channel' => 'whatsapp',
            'last_message_status' => $status,
        ]);

        return $conversation;
    }

    public function saveOutgoingSmsReply(
        Invitee $invitee,
        string $message,
        ?User $sender = null,
        ?string $providerMessageId = null,
        ?array $providerResponse = null,
        string $status = 'sent'
    ): InviteeConversation {
        $sender ??= Auth::user();

        $conversation = InviteeConversation::create([
            'event_id' => $invitee->event_id,
            'invitee_id' => $invitee->id,
            'sent_by' => $sender?->id,
            'channel' => 'sms',
            'direction' => 'outgoing',
            'from_phone' => null,
            'to_phone' => $invitee->phone,
            'message' => $message,
            'status' => $status,
            'provider_message_id' => $providerMessageId,
            'provider_response' => $providerResponse,
            'sent_at' => now(),
        ]);

        $invitee->update([
            'last_sms_sent_at' => now(),
            'last_message_channel' => 'sms',
            'last_message_status' => $status,
        ]);

        return $conversation;
    }

    public function updateMessageStatus(
        ?string $providerMessageId,
        string $status,
        ?array $providerResponse = null
    ): ?InviteeConversation {
        if (! $providerMessageId) {
            return null;
        }

        $conversation = InviteeConversation::where('provider_message_id', $providerMessageId)->first();

        if (! $conversation) {
            return null;
        }

        $updateData = [
            'status' => $status,
        ];

        if ($providerResponse !== null) {
            $updateData['provider_response'] = $providerResponse;
        }

        if ($status === 'delivered') {
            $updateData['status'] = 'delivered';
        }

        if ($status === 'read') {
            $updateData['status'] = 'read';
        }

        if ($status === 'failed') {
            $updateData['status'] = 'failed';
        }

        $conversation->update($updateData);

        $conversation->invitee?->update([
            'last_message_channel' => $conversation->channel,
            'last_message_status' => $status,
        ]);

        return $conversation;
    }
}