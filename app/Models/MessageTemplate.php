<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageTemplate extends Model
{
    protected $fillable = [
        'event_id',
        'channel',
        'type',
        'name',
        'content',
        'whatsapp_template_name',
        'whatsapp_buttons',
        'status',
    ];

    protected $casts = [
        'whatsapp_buttons' => 'array',
    ];

    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const TYPE_INVITATION = 'invitation';
    public const TYPE_RSVP_PENDING_REMINDER = 'rsvp_pending_reminder';
    public const TYPE_ATTENDING_REMINDER = 'attending_reminder';
    public const TYPE_EVENT_DAY_REMINDER = 'event_day_reminder';
    public const TYPE_CUSTOM = 'custom';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public static function channels(): array
    {
        return [
            self::CHANNEL_SMS => 'SMS',
            self::CHANNEL_WHATSAPP => 'WhatsApp',
        ];
    }

    public static function types(): array
    {
        return [
            self::TYPE_INVITATION => 'Invitation',
            self::TYPE_RSVP_PENDING_REMINDER => 'RSVP Pending Reminder',
            self::TYPE_ATTENDING_REMINDER => 'Attending Reminder',
            self::TYPE_EVENT_DAY_REMINDER => 'Event Day Reminder',
            self::TYPE_CUSTOM => 'Custom Message',
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_INACTIVE => 'Inactive',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}