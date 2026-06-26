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
        'provider_status',
        'provider_request',
        'provider_response',
        'error_message',

        'sent_at',
        'delivered_at',
        'failed_at',
        'delivery_report_checked_at',
    ];

    protected $casts = [
        'provider_request' => 'array',
        'provider_response' => 'array',

        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
        'delivery_report_checked_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Status Constants
    |--------------------------------------------------------------------------
    */

    public const STATUS_PENDING = 'pending';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_FAILED = 'failed';
    public const STATUS_UNDELIVERED = 'undelivered';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_UNKNOWN = 'unknown';

    /*
    |--------------------------------------------------------------------------
    | Provider Status Constants
    |--------------------------------------------------------------------------
    */

    public const PROVIDER_STATUS_DELIVERY_REPORT_UNAVAILABLE = 'delivery_report_unavailable';

    /*
    |--------------------------------------------------------------------------
    | SMS Type Constants
    |--------------------------------------------------------------------------
    */

    public const TYPE_INVITATION = 'invitation';
    public const TYPE_INVITATION_CARD = 'invitation_card';
    public const TYPE_RSVP_PENDING_REMINDER = 'rsvp_pending_reminder';
    public const TYPE_ATTENDING_REMINDER = 'attending_reminder';
    public const TYPE_EVENT_DAY_REMINDER = 'event_day_reminder';
    public const TYPE_CUSTOM = 'custom';
    public const TYPE_FALLBACK = 'fallback';

    /*
    |--------------------------------------------------------------------------
    | Send Source Constants
    |--------------------------------------------------------------------------
    */

    public const SOURCE_SYSTEM = 'system';
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_BULK_MANUAL = 'bulk_manual';
    public const SOURCE_AUTOMATIC = 'automatic';
    public const SOURCE_INVITEE_ACTION = 'invitee_action';
    public const SOURCE_REMINDER_PAGE = 'reminder_page';

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_QUEUED => 'Queued',
            self::STATUS_SENDING => 'Sending',
            self::STATUS_SENT => 'Sent',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_UNDELIVERED => 'Undelivered',
            self::STATUS_EXPIRED => 'Expired',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_UNKNOWN => 'Unknown',
        ];
    }

    public static function providerStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_QUEUED => 'Queued',
            self::STATUS_SENDING => 'Sending',
            self::STATUS_SENT => 'Sent',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_UNDELIVERED => 'Undelivered',
            self::STATUS_EXPIRED => 'Expired',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_UNKNOWN => 'Unknown',
            self::PROVIDER_STATUS_DELIVERY_REPORT_UNAVAILABLE => 'Delivery Report Unavailable',
        ];
    }

    public static function smsTypes(): array
    {
        return [
            self::TYPE_INVITATION => 'Invitation SMS',
            self::TYPE_INVITATION_CARD => 'Invitation Card',
            self::TYPE_RSVP_PENDING_REMINDER => 'RSVP Pending Reminder',
            self::TYPE_ATTENDING_REMINDER => 'One Day Before Reminder',
            self::TYPE_EVENT_DAY_REMINDER => 'Event Day Reminder',
            self::TYPE_CUSTOM => 'Custom SMS',
            self::TYPE_FALLBACK => 'Fallback SMS',
        ];
    }

    public static function sources(): array
    {
        return [
            self::SOURCE_SYSTEM => 'System',
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

    public function scopeQueued(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_QUEUED);
    }

    public function scopeSending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SENDING);
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

    public function scopeUndelivered(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_UNDELIVERED);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_EXPIRED);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_SENT,
            self::STATUS_DELIVERED,
        ]);
    }

    public function scopeDeliveryFailed(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_FAILED,
            self::STATUS_UNDELIVERED,
            self::STATUS_EXPIRED,
            self::STATUS_REJECTED,
        ]);
    }

    public function scopeDeliveryReportUnavailable(Builder $query): Builder
    {
        return $query->where('provider_status', self::PROVIDER_STATUS_DELIVERY_REPORT_UNAVAILABLE);
    }

    public function scopeNeedsDeliveryCheck(Builder $query): Builder
    {
        return $query
            ->whereNotNull('provider_message_id')
            ->whereIn('status', [
                self::STATUS_SENT,
                self::STATUS_UNKNOWN,
                self::STATUS_QUEUED,
                self::STATUS_SENDING,
            ])
            ->whereNull('delivered_at')
            ->whereNull('failed_at');
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

    public function isQueued(): bool
    {
        return $this->status === self::STATUS_QUEUED;
    }

    public function isSending(): bool
    {
        return $this->status === self::STATUS_SENDING;
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

    public function isDeliveryFailed(): bool
    {
        return in_array($this->status, [
            self::STATUS_FAILED,
            self::STATUS_UNDELIVERED,
            self::STATUS_EXPIRED,
            self::STATUS_REJECTED,
        ], true);
    }

    public function hasDeliveryReportUnavailable(): bool
    {
        return $this->provider_status === self::PROVIDER_STATUS_DELIVERY_REPORT_UNAVAILABLE;
    }

    public function hasProviderMessageId(): bool
    {
        return filled($this->provider_message_id);
    }

    public function isAutomatic(): bool
    {
        return in_array($this->send_source, [
            self::SOURCE_SYSTEM,
            self::SOURCE_AUTOMATIC,
        ], true);
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

    public function markAsSent(?string $providerMessageId = null, mixed $providerResponse = null): bool
    {
        return $this->update([
            'status' => self::STATUS_SENT,
            'provider_status' => self::STATUS_SENT,
            'provider_message_id' => $providerMessageId ?? $this->provider_message_id,
            'provider_response' => $providerResponse ?? $this->provider_response,
            'sent_at' => $this->sent_at ?? now(),
            'failed_at' => null,
            'error_message' => null,
        ]);
    }

    public function markAsDelivered(mixed $providerResponse = null): bool
    {
        return $this->update([
            'status' => self::STATUS_DELIVERED,
            'provider_status' => self::STATUS_DELIVERED,
            'provider_response' => $providerResponse ?? $this->provider_response,
            'delivered_at' => now(),
            'failed_at' => null,
            'error_message' => null,
            'delivery_report_checked_at' => now(),
        ]);
    }

    public function markAsFailed(?string $message = null, mixed $providerResponse = null, ?string $providerStatus = null): bool
    {
        return $this->update([
            'status' => self::STATUS_FAILED,
            'provider_status' => $providerStatus ?? self::STATUS_FAILED,
            'provider_response' => $providerResponse ?? $this->provider_response,
            'failed_at' => now(),
            'error_message' => $message,
            'delivery_report_checked_at' => now(),
        ]);
    }

    public function markDeliveryReportUnavailable(?string $message = null): bool
    {
        return $this->update([
            /*
             * Important:
             * Do not mark the SMS as failed.
             * The SMS was submitted successfully; only the delivery report API is unavailable.
             */
            'status' => $this->status ?: self::STATUS_SENT,
            'provider_status' => self::PROVIDER_STATUS_DELIVERY_REPORT_UNAVAILABLE,
            'error_message' => $message,
            'delivery_report_checked_at' => now(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getSmsTypeLabelAttribute(): string
    {
        return self::smsTypes()[$this->sms_type]
            ?? ucfirst(str_replace('_', ' ', (string) $this->sms_type));
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statuses()[$this->status]
            ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    public function getProviderStatusLabelAttribute(): string
    {
        return self::providerStatuses()[$this->provider_status]
            ?? ucfirst(str_replace('_', ' ', (string) $this->provider_status ?: 'unknown'));
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

    public function getDeliveryDisplayAttribute(): string
    {
        if ($this->hasDeliveryReportUnavailable()) {
            return 'Sent · Delivery report unavailable';
        }

        return $this->status_label;
    }
}