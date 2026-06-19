<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeSms(Builder $query): Builder
    {
        return $query->where('channel', self::CHANNEL_SMS);
    }

    public function scopeWhatsapp(Builder $query): Builder
    {
        return $query->where('channel', self::CHANNEL_WHATSAPP);
    }

    public function scopeForEvent(Builder $query, int $eventId): Builder
    {
        return $query->where('event_id', $eventId);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public static function activeTemplate(
        int $eventId,
        string $channel,
        string $type
    ): ?self {
        return self::query()
            ->forEvent($eventId)
            ->where('channel', $channel)
            ->ofType($type)
            ->active()
            ->latest('updated_at')
            ->first();
    }

    public static function activeSmsTemplate(int $eventId, string $type): ?self
    {
        return self::activeTemplate(
            eventId: $eventId,
            channel: self::CHANNEL_SMS,
            type: $type,
        );
    }

    public static function activeWhatsappTemplate(int $eventId, string $type): ?self
    {
        return self::activeTemplate(
            eventId: $eventId,
            channel: self::CHANNEL_WHATSAPP,
            type: $type,
        );
    }

    public function isSms(): bool
    {
        return $this->channel === self::CHANNEL_SMS;
    }

    public function isWhatsapp(): bool
    {
        return $this->channel === self::CHANNEL_WHATSAPP;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getChannelLabelAttribute(): string
    {
        return self::channels()[$this->channel] ?? ucfirst((string) $this->channel);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::types()[$this->type] ?? ucfirst(str_replace('_', ' ', (string) $this->type));
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statuses()[$this->status] ?? ucfirst((string) $this->status);
    }

    public function getPreviewAttribute(): string
    {
        return str($this->content ?? '')
            ->replace(["\r\n", "\n", "\r"], ' ')
            ->limit(90)
            ->toString();
    }
}