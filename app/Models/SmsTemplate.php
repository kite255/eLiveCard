<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsTemplate extends Model
{
    protected $fillable = [
        'event_id',
        'name',
        'sms_type',
        'message',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public static function smsTypes(): array
    {
        return [
            SmsLog::TYPE_INVITATION => 'Invitation SMS',
            SmsLog::TYPE_RSVP_PENDING_REMINDER => 'RSVP Pending Reminder',
            SmsLog::TYPE_ATTENDING_REMINDER => 'One Day Before Reminder',
            SmsLog::TYPE_EVENT_DAY_REMINDER => 'Event Day Reminder',
        ];
    }

    public static function placeholders(): array
    {
        return [
            '{name}' => 'Invitee full name',
            '{phone}' => 'Invitee phone number',

            '{event_name}' => 'Event title/name',
            '{event_type}' => 'Event type',
            '{event_date}' => 'Event date',
            '{event_time}' => 'Event start time',
            '{event_end_time}' => 'Event end time',

            '{venue}' => 'Venue name or address',
            '{venue_name}' => 'Venue name',
            '{venue_address}' => 'Venue address',
            '{google_maps_link}' => 'Google Maps link',

            '{dress_code}' => 'Dress code',
            '{program}' => 'Event program',

            '{contact_person_name}' => 'Organizer/contact person name',
            '{contact_person_phone}' => 'Organizer/contact person phone',

            '{card_type}' => 'Invitee card type',
            '{guest_count}' => 'Allowed guest count',
            '{allowed_guests}' => 'Allowed guest count',

            '{category}' => 'Invitee category',
            '{table_number}' => 'Table number',

            '{serial_number}' => 'Invitation serial number',
            '{short_code}' => 'Private short code',

            '{private_url}' => 'Private invitee page link',
            '{rsvp_link}' => 'Private RSVP link',
            '{qr_code_url}' => 'QR code image URL',
        ];
    }

    public static function placeholderKeys(): array
    {
        return array_keys(self::placeholders());
    }

    public static function placeholderText(): string
    {
        return collect(self::placeholders())
            ->map(fn (string $description, string $placeholder): string => "{$placeholder} - {$description}")
            ->implode("\n");
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}