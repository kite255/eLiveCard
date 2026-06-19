<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Invitee;
use Carbon\Carbon;

class MessageTemplateRenderer
{
    public function render(string $content, Invitee $invitee): string
    {
        $invitee->loadMissing(['event', 'cardType']);

        $event = $invitee->event;
        $cardType = $invitee->cardType;

        return str_replace([
            '{name}',
            '{event_name}',
            '{event_date}',
            '{event_time}',
            '{venue}',
            '{venue_name}',
            '{venue_address}',
            '{private_invitation_url}',
            '{location_link}',
            '{card_type}',
            '{serial_number}',
            '{table_number}',
            '{allowed_guests}',
        ], [
            $invitee->name ?? '',
            $event?->title ?? $event?->name ?? '',
            $this->formatDate($event),
            $this->formatTime($event),
            $this->venue($event),
            $event?->venue_name ?? '',
            $event?->venue_address ?? '',
            filled($invitee->short_code) ? route('invitee.page', $invitee->short_code) : '',
            $event?->google_maps_link ?? '',
            $cardType?->name ?? '',
            $invitee->serial_number ?? '',
            $invitee->table_number ?? '',
            $invitee->allowed_guests ?? $cardType?->allowed_people ?? '',
        ], $content);
    }

    private function formatDate(?Event $event): string
    {
        if (! $event || blank($event->event_date)) {
            return '';
        }

        return Carbon::parse($event->event_date)->format('d/m/Y');
    }

    private function formatTime(?Event $event): string
    {
        if (! $event || blank($event->start_time)) {
            return '';
        }

        return Carbon::parse($event->start_time)->format('H:i');
    }

    private function venue(?Event $event): string
    {
        if (! $event) {
            return '';
        }

        return trim(($event->venue_name ?? '') . ' - ' . ($event->venue_address ?? ''), ' -');
    }
}
