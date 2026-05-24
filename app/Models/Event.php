<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'event_type',
        'event_date',
        'start_time',
        'end_time',
        'venue_name',
        'venue_address',
        'google_maps_link',
        'dress_code',
        'program',
        'contact_person_name',
        'contact_person_phone',
        'status',

        // Automatic SMS reminder settings
        'auto_sms_reminders_enabled',
        'auto_rsvp_pending_reminder_enabled',
        'auto_one_day_reminder_enabled',
        'auto_event_day_reminder_enabled',
    ];

    protected $casts = [
        'event_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',

        // Automatic SMS reminder settings
        'auto_sms_reminders_enabled' => 'boolean',
        'auto_rsvp_pending_reminder_enabled' => 'boolean',
        'auto_one_day_reminder_enabled' => 'boolean',
        'auto_event_day_reminder_enabled' => 'boolean',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cardTypes(): HasMany
    {
        return $this->hasMany(CardType::class);
    }

    public function invitees(): HasMany
    {
        return $this->hasMany(Invitee::class);
    }

    public function cardTemplates(): HasMany
    {
        return $this->hasMany(CardTemplate::class);
    }

    public function activeCardTemplates(): HasMany
    {
        return $this->hasMany(CardTemplate::class)
            ->where('status', CardTemplate::STATUS_ACTIVE);
    }

    public function generatedCards(): HasMany
    {
        return $this->hasMany(GeneratedCard::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    public function smsLogs(): HasMany
    {
        return $this->hasMany(SmsLog::class);
    }

    public function smsTemplates(): HasMany
    {
        return $this->hasMany(SmsTemplate::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeAutomaticSmsEnabled($query)
    {
        return $query->where('auto_sms_reminders_enabled', true);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function hasAutomaticSmsRemindersEnabled(): bool
    {
        return (bool) $this->auto_sms_reminders_enabled;
    }

    public function canAutoSendRsvpPendingReminder(): bool
    {
        return $this->hasAutomaticSmsRemindersEnabled()
            && (bool) $this->auto_rsvp_pending_reminder_enabled;
    }

    public function canAutoSendOneDayReminder(): bool
    {
        return $this->hasAutomaticSmsRemindersEnabled()
            && (bool) $this->auto_one_day_reminder_enabled;
    }

    public function canAutoSendEventDayReminder(): bool
    {
        return $this->hasAutomaticSmsRemindersEnabled()
            && (bool) $this->auto_event_day_reminder_enabled;
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->title;
    }

    public function getVenueDisplayAttribute(): string
    {
        return $this->venue_name ?: $this->venue_address ?: 'Venue not set';
    }

    public function getEventDateDisplayAttribute(): string
    {
        return $this->event_date
            ? $this->event_date->format('d M Y')
            : 'Date not set';
    }

    public function getStartTimeDisplayAttribute(): string
    {
        return $this->start_time
            ? $this->start_time->format('H:i')
            : 'Time not set';
    }

    public function getSmsSentCountAttribute(): int
    {
        return $this->smsLogs()
            ->whereIn('status', [
                SmsLog::STATUS_SENT,
                SmsLog::STATUS_DELIVERED,
            ])
            ->count();
    }

    public function getSmsFailedCountAttribute(): int
    {
        return $this->smsLogs()
            ->where('status', SmsLog::STATUS_FAILED)
            ->count();
    }

    public function getInvitationSmsSentCountAttribute(): int
    {
        return $this->smsLogs()
            ->where('sms_type', SmsLog::TYPE_INVITATION)
            ->whereIn('status', [
                SmsLog::STATUS_SENT,
                SmsLog::STATUS_DELIVERED,
            ])
            ->count();
    }

    public function getReminderSmsSentCountAttribute(): int
    {
        return $this->smsLogs()
            ->whereIn('sms_type', [
                SmsLog::TYPE_RSVP_PENDING_REMINDER,
                SmsLog::TYPE_ATTENDING_REMINDER,
            ])
            ->whereIn('status', [
                SmsLog::STATUS_SENT,
                SmsLog::STATUS_DELIVERED,
            ])
            ->count();
    }

    public function getFinalSmsSentCountAttribute(): int
    {
        return $this->smsLogs()
            ->where('sms_type', SmsLog::TYPE_EVENT_DAY_REMINDER)
            ->whereIn('status', [
                SmsLog::STATUS_SENT,
                SmsLog::STATUS_DELIVERED,
            ])
            ->count();
    }
}