<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsLog extends Model
{
    protected $fillable = [
        'event_id',
        'invitee_id',
        'phone',
        'sms_type',

        // Audit/reporting
        'send_source',
        'sent_by_user_id',
        'batch_id',

        'message',
        'status',
        'provider',
        'provider_message_id',
        'error_message',

        'sent_at',
        'delivered_at',
        'failed_at',

        'provider_response',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
        'provider_response' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Status Constants
    |--------------------------------------------------------------------------
    */

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_FAILED = 'failed';

    /*
    |--------------------------------------------------------------------------
    | SMS Type Constants
    |--------------------------------------------------------------------------
    */

    public const TYPE_INVITATION = 'invitation';
    public const TYPE_RSVP_PENDING_REMINDER = 'rsvp_pending_reminder';
    public const TYPE_ATTENDING_REMINDER = 'attending_reminder';
    public const TYPE_EVENT_DAY_REMINDER = 'event_day_reminder';

    /*
    |--------------------------------------------------------------------------
    | Send Source Constants
    |--------------------------------------------------------------------------
    */

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_BULK_MANUAL = 'bulk_manual';
    public const SOURCE_AUTOMATIC = 'automatic';
    public const SOURCE_INVITEE_ACTION = 'invitee_action';
    public const SOURCE_REMINDER_PAGE = 'reminder_page';

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_SENT => 'Sent',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_FAILED => 'Failed',
        ];
    }

    public static function smsTypes(): array
    {
        return [
            self::TYPE_INVITATION => 'Invitation SMS',
            self::TYPE_RSVP_PENDING_REMINDER => 'RSVP Pending Reminder',
            self::TYPE_ATTENDING_REMINDER => 'One Day Before Reminder',
            self::TYPE_EVENT_DAY_REMINDER => 'Event Day Reminder',
        ];
    }

    public static function sources(): array
    {
        return [
            self::SOURCE_MANUAL => 'Manual',
            self::SOURCE_BULK_MANUAL => 'Bulk Manual',
            self::SOURCE_AUTOMATIC => 'Automatic',
            self::SOURCE_INVITEE_ACTION => 'Invitee Action',
            self::SOURCE_REMINDER_PAGE => 'Reminder Page',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(Invitee::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SENT);
    }

    public function scopeDelivered(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DELIVERED);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_SENT,
            self::STATUS_DELIVERED,
        ]);
    }

    public function scopeForBatch(Builder $query, ?string $batchId): Builder
    {
        return $query->where('batch_id', $batchId);
    }

    public function scopeForEvent(Builder $query, int $eventId): Builder
    {
        return $query->where('event_id', $eventId);
    }

    public function scopeForSmsType(Builder $query, string $smsType): Builder
    {
        return $query->where('sms_type', $smsType);
    }

    public function scopeFromSource(Builder $query, string $source): Builder
    {
        return $query->where('send_source', $source);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isAutomatic(): bool
    {
        return $this->send_source === self::SOURCE_AUTOMATIC;
    }

    public function isManual(): bool
    {
        return in_array($this->send_source, [
            self::SOURCE_MANUAL,
            self::SOURCE_BULK_MANUAL,
            self::SOURCE_INVITEE_ACTION,
            self::SOURCE_REMINDER_PAGE,
        ], true);
    }

    public function getSmsTypeLabelAttribute(): string
    {
        return self::smsTypes()[$this->sms_type]
            ?? ucfirst(str_replace('_', ' ', (string) $this->sms_type));
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statuses()[$this->status]
            ?? ucfirst((string) $this->status);
    }

    public function getSourceLabelAttribute(): string
    {
        return self::sources()[$this->send_source]
            ?? ucfirst(str_replace('_', ' ', (string) $this->send_source));
    }

    public function getShortBatchIdAttribute(): string
    {
        return $this->batch_id
            ? substr($this->batch_id, 0, 8)
            : '-';
    }
}