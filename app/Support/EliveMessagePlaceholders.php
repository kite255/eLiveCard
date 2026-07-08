<?php

namespace App\Support;

use App\Models\Invitee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

class EliveMessagePlaceholders
{
    public static function keys(): array
    {
        return [
            '#NAME#',
            '#PHONE#',
            '#EVENT_NAME#',
            '#EVENT_DATE#',
            '#EVENT_TIME#',
            '#VENUE#',
            '#VENUE_ADDRESS#',
            '#LOCATION_LINK#',
            '#DRESS_CODE#',
            '#CARD_TYPE#',
            '#ALLOWED_GUESTS#',
            '#TABLE_NUMBER#',
            '#CATEGORY#',
            '#SERIAL_NUMBER#',
            '#PRIVATE_INVITATION_URL#',
            '#RSVP_URL#',
            '#ORGANIZER_PHONE#',
        ];
    }

    public static function helperText(): string
    {
        return 'Placeholders: ' . implode(', ', self::keys()) . '.';
    }

    public static function shortHelperText(): string
    {
        return 'Common placeholders: #NAME#, #EVENT_NAME#, #EVENT_DATE#, #EVENT_TIME#, #VENUE#, #PRIVATE_INVITATION_URL#, #RSVP_URL#.';
    }

    public static function replacements(Invitee $invitee): array
    {
        $invitee->loadMissing(['event', 'cardType']);

        $event = $invitee->event;

        $eventName = $event?->name
            ?? $event?->title
            ?? '';

        $eventDate = $event?->event_date
            ?? $event?->date
            ?? null;

        $eventTime = $event?->start_time
            ?? $event?->time
            ?? null;

        $formattedDate = self::formatDate($eventDate);
        $formattedTime = self::formatTime($eventTime);

        $venue = $event?->venue_name
            ?? $event?->venue
            ?? $event?->location
            ?? '';

        $venueAddress = $event?->venue_address ?? '';

        $locationLink = $event?->google_maps_link
            ?? $event?->map_link
            ?? '';

        $dressCode = $event?->dress_code ?? '';

        $organizerPhone = $event?->organizer_phone
            ?? $event?->contact_person_phone
            ?? $event?->contact_phone
            ?? $event?->phone
            ?? '';

        $cardType = $invitee->cardType?->name
            ?? $invitee->card_type
            ?? $invitee->card_type_name
            ?? '';

        $allowedGuests = $invitee->final_allowed_guests
            ?? $invitee->allowed_guests
            ?? $invitee->cardType?->allowed_guests
            ?? $invitee->cardType?->allowed_people
            ?? 1;

        return [
            '#NAME#' => (string) ($invitee->name ?? ''),
            '#PHONE#' => (string) ($invitee->phone ?? ''),
            '#EVENT_NAME#' => (string) $eventName,
            '#EVENT_DATE#' => (string) $formattedDate,
            '#EVENT_TIME#' => (string) $formattedTime,
            '#VENUE#' => (string) $venue,
            '#VENUE_ADDRESS#' => (string) $venueAddress,
            '#LOCATION_LINK#' => (string) $locationLink,
            '#DRESS_CODE#' => (string) $dressCode,
            '#CARD_TYPE#' => (string) $cardType,
            '#ALLOWED_GUESTS#' => (string) $allowedGuests,
            '#TABLE_NUMBER#' => (string) ($invitee->table_number ?? ''),
            '#CATEGORY#' => (string) ($invitee->category ?? ''),
            '#SERIAL_NUMBER#' => (string) ($invitee->serial_number ?? ''),
            '#PRIVATE_INVITATION_URL#' => self::privateInvitationUrl($invitee),
            '#RSVP_URL#' => self::rsvpUrl($invitee),
            '#ORGANIZER_PHONE#' => (string) $organizerPhone,
        ];
    }

    public static function render(?string $message, Invitee $invitee): string
    {
        if (blank($message)) {
            return '';
        }

        $message = self::normalizeLegacyPlaceholders($message);

        $replacements = self::replacements($invitee);

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $message
        );
    }

    public static function normalizeLegacyPlaceholders(string $message): string
    {
        /*
         |--------------------------------------------------------------------------
         | Convert old placeholder formats to the new standard
         |--------------------------------------------------------------------------
         |
         | New standard:
         | #NAME#, #EVENT_NAME#, #VENUE#
         |
         | This method intentionally avoids replacing #NAME# with #NAME##
         | by only fixing incomplete hashtags when they are not already closed.
         |
         */

        $legacyMap = [
            // Old curly format
            '{name}' => '#NAME#',
            '{phone}' => '#PHONE#',
            '{event_name}' => '#EVENT_NAME#',
            '{event_date}' => '#EVENT_DATE#',
            '{event_time}' => '#EVENT_TIME#',
            '{venue}' => '#VENUE#',
            '{venue_address}' => '#VENUE_ADDRESS#',
            '{location_link}' => '#LOCATION_LINK#',
            '{dress_code}' => '#DRESS_CODE#',
            '{card_type}' => '#CARD_TYPE#',
            '{allowed_guests}' => '#ALLOWED_GUESTS#',
            '{table_number}' => '#TABLE_NUMBER#',
            '{category}' => '#CATEGORY#',
            '{serial_number}' => '#SERIAL_NUMBER#',
            '{private_invitation_url}' => '#PRIVATE_INVITATION_URL#',
            '{rsvp_url}' => '#RSVP_URL#',
            '{organizer_phone}' => '#ORGANIZER_PHONE#',

            // Old blade-like format
            '{{name}}' => '#NAME#',
            '{{phone}}' => '#PHONE#',
            '{{event_name}}' => '#EVENT_NAME#',
            '{{event_date}}' => '#EVENT_DATE#',
            '{{event_time}}' => '#EVENT_TIME#',
            '{{venue}}' => '#VENUE#',
            '{{venue_address}}' => '#VENUE_ADDRESS#',
            '{{location_link}}' => '#LOCATION_LINK#',
            '{{dress_code}}' => '#DRESS_CODE#',
            '{{card_type}}' => '#CARD_TYPE#',
            '{{allowed_guests}}' => '#ALLOWED_GUESTS#',
            '{{table_number}}' => '#TABLE_NUMBER#',
            '{{category}}' => '#CATEGORY#',
            '{{serial_number}}' => '#SERIAL_NUMBER#',
            '{{private_invitation_url}}' => '#PRIVATE_INVITATION_URL#',
            '{{rsvp_url}}' => '#RSVP_URL#',
            '{{organizer_phone}}' => '#ORGANIZER_PHONE#',

            // Old hashtag aliases
            '#INVITATION_LINK#' => '#PRIVATE_INVITATION_URL#',
            '#CARD_LINK#' => '#PRIVATE_INVITATION_URL#',
            '#RSVP_LINK#' => '#RSVP_URL#',
            '#GUEST_COUNT#' => '#ALLOWED_GUESTS#',
            '#EVENT_VENUE#' => '#VENUE#',
        ];

        $message = str_replace(
            array_keys($legacyMap),
            array_values($legacyMap),
            $message
        );

        /*
         |--------------------------------------------------------------------------
         | Fix incomplete hashtag placeholders only
         |--------------------------------------------------------------------------
         |
         | Example:
         | #NAME becomes #NAME#
         |
         | But:
         | #NAME# stays #NAME#
         |
         */
        $incompleteHashtags = [
            '#PRIVATE_INVITATION_URL' => '#PRIVATE_INVITATION_URL#',
            '#ORGANIZER_PHONE' => '#ORGANIZER_PHONE#',
            '#ALLOWED_GUESTS' => '#ALLOWED_GUESTS#',
            '#SERIAL_NUMBER' => '#SERIAL_NUMBER#',
            '#VENUE_ADDRESS' => '#VENUE_ADDRESS#',
            '#LOCATION_LINK' => '#LOCATION_LINK#',
            '#TABLE_NUMBER' => '#TABLE_NUMBER#',
            '#EVENT_NAME' => '#EVENT_NAME#',
            '#EVENT_DATE' => '#EVENT_DATE#',
            '#EVENT_TIME' => '#EVENT_TIME#',
            '#DRESS_CODE' => '#DRESS_CODE#',
            '#CARD_TYPE' => '#CARD_TYPE#',
            '#CATEGORY' => '#CATEGORY#',
            '#RSVP_URL' => '#RSVP_URL#',
            '#PHONE' => '#PHONE#',
            '#VENUE' => '#VENUE#',
            '#NAME' => '#NAME#',
        ];

        foreach ($incompleteHashtags as $from => $to) {
            $message = preg_replace(
                '/' . preg_quote($from, '/') . '(?!#)/',
                $to,
                $message
            );
        }

        return $message;
    }

    protected static function formatDate(mixed $date): string
    {
        if (blank($date)) {
            return '';
        }

        try {
            return Carbon::parse($date)->format('d M Y');
        } catch (\Throwable) {
            return (string) $date;
        }
    }

    protected static function formatTime(mixed $time): string
    {
        if (blank($time)) {
            return '';
        }

        try {
            return Carbon::parse($time)->format('h:i A');
        } catch (\Throwable) {
            return (string) $time;
        }
    }

    protected static function privateInvitationUrl(Invitee $invitee): string
    {
        if (filled($invitee->private_invitation_url ?? null)) {
            return (string) $invitee->private_invitation_url;
        }

        if (filled($invitee->short_code ?? null) && Route::has('invitee.page')) {
            return route('invitee.page', $invitee->short_code);
        }

        return '';
    }

    protected static function rsvpUrl(Invitee $invitee): string
    {
        if (filled($invitee->rsvp_url ?? null)) {
            return (string) $invitee->rsvp_url;
        }

        if (filled($invitee->rsvp_token ?? null) && Route::has('rsvp.show')) {
            return route('rsvp.show', $invitee->rsvp_token);
        }

        if (filled($invitee->short_code ?? null) && Route::has('invitee.page')) {
            return route('invitee.page', $invitee->short_code) . '#rsvp';
        }

        return '';
    }
}