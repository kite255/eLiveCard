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
        'whatsapp_language_code',
        'whatsapp_buttons',
        'status',
    ];

    protected $casts = [
        'whatsapp_buttons' => 'array',
    ];

    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const TYPE_INVITATION = 'invitation';
    public const TYPE_CONTRIBUTION = 'contribution';
    public const TYPE_RSVP_PENDING_REMINDER = 'rsvp_pending_reminder';
    public const TYPE_ATTENDING_REMINDER = 'attending_reminder';
    public const TYPE_EVENT_DAY_REMINDER = 'event_day_reminder';
    public const TYPE_CUSTOM = 'custom';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public const LANGUAGE_ENGLISH = 'en';
    public const LANGUAGE_SWAHILI = 'sw';

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
            self::TYPE_CONTRIBUTION => 'Contribution Card',
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

    public static function languages(): array
    {
        return [
            self::LANGUAGE_ENGLISH => 'English',
            self::LANGUAGE_SWAHILI => 'Swahili',
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

    public function scopeForEventOrGlobal(
        Builder $query,
        int $eventId
    ): Builder {
        return $query->where(function (Builder $query) use ($eventId) {
            $query->where('event_id', $eventId)
                ->orWhereNull('event_id');
        });
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeOfLanguage(
        Builder $query,
        string $language
    ): Builder {
        return $query->where('whatsapp_language_code', $language);
    }

    public static function activeTemplate(
        int $eventId,
        string $channel,
        string $type,
        ?string $language = null
    ): ?self {
        return self::query()
            ->where('channel', $channel)
            ->ofType($type)
            ->when(
                $channel === self::CHANNEL_WHATSAPP && filled($language),
                fn (Builder $query) => $query->ofLanguage($language)
            )
            ->active()
            ->forEventOrGlobal($eventId)
            ->orderByRaw(
                'CASE WHEN event_id = ? THEN 0 ELSE 1 END',
                [$eventId]
            )
            ->latest('updated_at')
            ->first();
    }

    public static function activeSmsTemplate(
        int $eventId,
        string $type
    ): ?self {
        return self::activeTemplate(
            eventId: $eventId,
            channel: self::CHANNEL_SMS,
            type: $type,
        );
    }

    public static function activeWhatsappTemplate(
        int $eventId,
        string $type,
        string $language = self::LANGUAGE_ENGLISH
    ): ?self {
        return self::activeTemplate(
            eventId: $eventId,
            channel: self::CHANNEL_WHATSAPP,
            type: $type,
            language: $language,
        );
    }

    public static function activeEnglishWhatsappTemplate(
        int $eventId,
        string $type
    ): ?self {
        return self::activeWhatsappTemplate(
            eventId: $eventId,
            type: $type,
            language: self::LANGUAGE_ENGLISH,
        );
    }

    public static function activeSwahiliWhatsappTemplate(
        int $eventId,
        string $type
    ): ?self {
        return self::activeWhatsappTemplate(
            eventId: $eventId,
            type: $type,
            language: self::LANGUAGE_SWAHILI,
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

    public function isEnglish(): bool
    {
        return $this->whatsapp_language_code === self::LANGUAGE_ENGLISH;
    }

    public function isSwahili(): bool
    {
        return $this->whatsapp_language_code === self::LANGUAGE_SWAHILI;
    }

    public function getChannelLabelAttribute(): string
    {
        return self::channels()[$this->channel]
            ?? ucfirst((string) $this->channel);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::types()[$this->type]
            ?? ucfirst(str_replace('_', ' ', (string) $this->type));
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statuses()[$this->status]
            ?? ucfirst((string) $this->status);
    }

    public function getLanguageLabelAttribute(): string
    {
        return self::languages()[$this->whatsapp_language_code]
            ?? strtoupper((string) $this->whatsapp_language_code);
    }

    public function getPreviewAttribute(): string
    {
        return str($this->content ?? '')
            ->replace(["\r\n", "\n", "\r"], ' ')
            ->limit(90)
            ->toString();
    }
}