<?php

namespace App\Services;

use App\Models\Invitee;
use Illuminate\Support\Facades\Log;

class RsvpService
{
    public function confirmAttendance(Invitee $invitee, ?string $source = null, ?string $message = null): Invitee
    {
        $invitee->update([
            'rsvp_status' => Invitee::RSVP_STATUS_ATTENDING,
            'rsvp_confirmed_at' => now(),
            'last_message_channel' => $source ?: $invitee->last_message_channel,
            'last_message_status' => Invitee::MESSAGE_STATUS_REPLIED,
            'last_reply_message' => $message,
            'last_reply_at' => now(),
        ]);

        Log::info('Invitee confirmed attendance', [
            'invitee_id' => $invitee->id,
            'event_id' => $invitee->event_id,
            'phone' => $invitee->phone,
            'source' => $source,
        ]);

        return $invitee;
    }

    public function declineAttendance(Invitee $invitee, ?string $source = null, ?string $message = null): Invitee
    {
        $invitee->update([
            'rsvp_status' => Invitee::RSVP_STATUS_NOT_ATTENDING,
            'rsvp_confirmed_at' => now(),
            'last_message_channel' => $source ?: $invitee->last_message_channel,
            'last_message_status' => Invitee::MESSAGE_STATUS_REPLIED,
            'last_reply_message' => $message,
            'last_reply_at' => now(),
        ]);

        Log::info('Invitee declined attendance', [
            'invitee_id' => $invitee->id,
            'event_id' => $invitee->event_id,
            'phone' => $invitee->phone,
            'source' => $source,
        ]);

        return $invitee;
    }

    public function updateFromWhatsappButton(Invitee $invitee, string $buttonPayload, ?string $buttonTitle = null): Invitee
    {
        return match ($buttonPayload) {
            'rsvp_attending',
            'attending',
            'confirm_attendance' => $this->confirmAttendance(
                invitee: $invitee,
                source: Invitee::CHANNEL_WHATSAPP,
                message: $buttonTitle ?: $buttonPayload,
            ),

            'rsvp_not_attending',
            'not_attending',
            'decline_attendance' => $this->declineAttendance(
                invitee: $invitee,
                source: Invitee::CHANNEL_WHATSAPP,
                message: $buttonTitle ?: $buttonPayload,
            ),

            default => $invitee,
        };
    }
}