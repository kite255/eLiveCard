<?php

namespace App\Models;

use App\Support\EliveMessagePlaceholders;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

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

        // Invitee digital page content
        'cover_image',
        'welcome_message',
        'love_story',
        'program',
        'organizer_phone',
        'show_cover_image',
        'show_love_story',
        'show_program',
        'show_countdown',
        'show_wishes',
        'show_photo_upload',
        'show_organizer_contact',

        'contact_person_name',
        'contact_person_phone',
        'status',

        // Automatic SMS reminder settings
        'auto_sms_reminders_enabled',
        'auto_rsvp_pending_reminder_enabled',
        'rsvp_pending_reminder_time',
        'auto_one_day_reminder_enabled',
        'one_day_reminder_time',
        'auto_event_day_reminder_enabled',
        'event_day_reminder_time',

        // Optional welcome SMS after successful check-in
        'welcome_sms_enabled',
        'welcome_sms_message',
    ];

    protected $casts = [
        'event_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',

        'auto_sms_reminders_enabled' => 'boolean',
        'auto_rsvp_pending_reminder_enabled' => 'boolean',
        'rsvp_pending_reminder_time' => 'datetime:H:i',
        'auto_one_day_reminder_enabled' => 'boolean',
        'one_day_reminder_time' => 'datetime:H:i',
        'auto_event_day_reminder_enabled' => 'boolean',
        'event_day_reminder_time' => 'datetime:H:i',
        'welcome_sms_enabled' => 'boolean',

        // Invitee digital page toggles
        'show_cover_image' => 'boolean',
        'show_love_story' => 'boolean',
        'show_program' => 'boolean',
        'show_countdown' => 'boolean',
        'show_wishes' => 'boolean',
        'show_photo_upload' => 'boolean',
        'show_organizer_contact' => 'boolean',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_WEDDING = 'wedding';
    public const TYPE_SEND_OFF = 'send_off';
    public const TYPE_KITCHEN_PARTY = 'kitchen_party';
    public const TYPE_ENGAGEMENT = 'engagement';
    public const TYPE_BIRTHDAY = 'birthday';
    public const TYPE_GRADUATION = 'graduation';
    public const TYPE_ANNIVERSARY = 'anniversary';
    public const TYPE_BABY_SHOWER = 'baby_shower';
    public const TYPE_RELIGIOUS_CELEBRATION = 'religious_celebration';
    public const TYPE_PRIVATE_FAMILY_EVENT = 'private_family_event';
    public const TYPE_CUSTOM = 'custom';

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    public static function eventTypes(): array
    {
        return [
            self::TYPE_WEDDING => 'Wedding',
            self::TYPE_SEND_OFF => 'Send-off',
            self::TYPE_KITCHEN_PARTY => 'Kitchen Party',
            self::TYPE_ENGAGEMENT => 'Engagement',
            self::TYPE_BIRTHDAY => 'Birthday',
            self::TYPE_GRADUATION => 'Graduation',
            self::TYPE_ANNIVERSARY => 'Anniversary',
            self::TYPE_BABY_SHOWER => 'Baby Shower',
            self::TYPE_RELIGIOUS_CELEBRATION => 'Religious Celebration',
            self::TYPE_PRIVATE_FAMILY_EVENT => 'Private Family Event',
            self::TYPE_CUSTOM => 'Custom',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

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

    public function inviteeUploads(): HasMany
    {
        return $this->hasMany(InviteeUpload::class);
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

    public function messageTemplates(): HasMany
    {
        return $this->hasMany(MessageTemplate::class);
    }

    public function messageLogs(): HasMany
    {
        return $this->hasMany(MessageLog::class);
    }

    public function smsMessageLogs(): HasMany
    {
        return $this->hasMany(MessageLog::class)
            ->where('channel', 'sms');
    }

    public function whatsappMessageLogs(): HasMany
    {
        return $this->hasMany(MessageLog::class)
            ->where('channel', 'whatsapp');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeAutomaticSmsEnabled($query)
    {
        return $query->where('auto_sms_reminders_enabled', true);
    }

    public function scopeWelcomeSmsEnabled($query)
    {
        return $query->where('welcome_sms_enabled', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Status Helpers
    |--------------------------------------------------------------------------
    */

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /*
    |--------------------------------------------------------------------------
    | Automatic SMS Reminder Helpers
    |--------------------------------------------------------------------------
    */

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

    public function getEffectiveRsvpPendingReminderTimeAttribute(): string
    {
        return $this->rsvp_pending_reminder_time?->format('H:i') ?? '09:00';
    }

    public function getEffectiveOneDayReminderTimeAttribute(): string
    {
        return $this->one_day_reminder_time?->format('H:i') ?? '10:00';
    }

    public function getEffectiveEventDayReminderTimeAttribute(): string
    {
        return $this->event_day_reminder_time?->format('H:i') ?? '06:00';
    }

    public function isRsvpPendingReminderDue(?string $currentTime = null): bool
    {
        return $this->canAutoSendRsvpPendingReminder()
            && $this->effective_rsvp_pending_reminder_time === ($currentTime ?? now()->format('H:i'));
    }

    public function isOneDayReminderDue(?string $currentTime = null): bool
    {
        return $this->canAutoSendOneDayReminder()
            && $this->effective_one_day_reminder_time === ($currentTime ?? now()->format('H:i'));
    }

    public function isEventDayReminderDue(?string $currentTime = null): bool
    {
        return $this->canAutoSendEventDayReminder()
            && $this->effective_event_day_reminder_time === ($currentTime ?? now()->format('H:i'));
    }

    /*
    |--------------------------------------------------------------------------
    | Welcome SMS Helpers
    |--------------------------------------------------------------------------
    */

    public function hasWelcomeSmsEnabled(): bool
    {
        return (bool) $this->welcome_sms_enabled;
    }

    public function getEffectiveWelcomeSmsMessageAttribute(): string
    {
        return filled($this->welcome_sms_message)
            ? (string) $this->welcome_sms_message
            : 'Karibu #NAME# kwenye #EVENT_NAME#. Tunafurahi kuwa nawe. Furahia tukio hili maalum.';
    }

    public function renderWelcomeSms(Invitee $invitee): string
    {
        $invitee->loadMissing(['event', 'cardType']);

        return EliveMessagePlaceholders::render(
            $this->effective_welcome_sms_message,
            $invitee
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Invitee Digital Page Helpers
    |--------------------------------------------------------------------------
    */

    public function shouldShowCoverImage(): bool
    {
        return (bool) ($this->show_cover_image ?? true);
    }

    public function shouldShowLoveStory(): bool
    {
        return (bool) ($this->show_love_story ?? false);
    }

    public function shouldShowProgram(): bool
    {
        return (bool) ($this->show_program ?? true);
    }

    public function shouldShowCountdown(): bool
    {
        return (bool) ($this->show_countdown ?? true);
    }

    public function shouldShowWishes(): bool
    {
        return (bool) ($this->show_wishes ?? true);
    }

    public function shouldShowPhotoUpload(): bool
    {
        return (bool) ($this->show_photo_upload ?? true);
    }

    public function shouldShowOrganizerContact(): bool
    {
        return (bool) ($this->show_organizer_contact ?? true);
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        if (! filled($this->cover_image)) {
            return null;
        }

        if (filter_var($this->cover_image, FILTER_VALIDATE_URL)) {
            return $this->cover_image;
        }

        return Storage::disk('public')->exists($this->cover_image)
            ? Storage::disk('public')->url($this->cover_image)
            : null;
    }

    public function getEffectiveWelcomeMessageAttribute(): ?string
    {
        return filled($this->welcome_message)
            ? (string) $this->welcome_message
            : null;
    }

    public function getEffectiveOrganizerPhoneAttribute(): ?string
    {
        return $this->organizer_phone
            ?: $this->contact_person_phone
            ?: config('app.organizer_phone')
            ?: config('services.elive.contact_phone');
    }

    public function getProgramItemsAttribute(): array
    {
        if (! filled($this->program)) {
            return [
                'Guest Arrival',
                'Opening Prayer',
                'Welcome Remarks',
                'Main Ceremony',
                'Photos',
                'Closing',
            ];
        }

        return collect(preg_split('/\r\n|\r|\n/', (string) $this->program))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values()
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Display Accessors
    |--------------------------------------------------------------------------
    */

    public function getNameAttribute(): ?string
    {
        return $this->title;
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->title ?: 'Untitled Event';
    }

    public function getEventTypeDisplayAttribute(): string
    {
        if (! $this->event_type) {
            return 'Not set';
        }

        return self::eventTypes()[$this->event_type] ?? ucfirst(str_replace('_', ' ', (string) $this->event_type));
    }

    public function getStatusDisplayAttribute(): string
    {
        return self::statuses()[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    public function getVenueDisplayAttribute(): string
    {
        return $this->venue_name ?: $this->venue_address ?: 'Venue not set';
    }

    public function getFullVenueDisplayAttribute(): string
    {
        if ($this->venue_name && $this->venue_address) {
            return $this->venue_name . ', ' . $this->venue_address;
        }

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

    public function getEndTimeDisplayAttribute(): string
    {
        return $this->end_time
            ? $this->end_time->format('H:i')
            : 'Time not set';
    }

    public function getRsvpPendingReminderTimeDisplayAttribute(): string
    {
        return $this->effective_rsvp_pending_reminder_time;
    }

    public function getOneDayReminderTimeDisplayAttribute(): string
    {
        return $this->effective_one_day_reminder_time;
    }

    public function getEventDayReminderTimeDisplayAttribute(): string
    {
        return $this->effective_event_day_reminder_time;
    }

    public function getTimeDisplayAttribute(): string
    {
        if ($this->start_time && $this->end_time) {
            return $this->start_time->format('H:i') . ' - ' . $this->end_time->format('H:i');
        }

        if ($this->start_time) {
            return $this->start_time->format('H:i');
        }

        return 'Time not set';
    }

    public function getContactDisplayAttribute(): string
    {
        if ($this->contact_person_name && $this->contact_person_phone) {
            return $this->contact_person_name . ' - ' . $this->contact_person_phone;
        }

        return $this->contact_person_name ?: $this->contact_person_phone ?: 'Contact not set';
    }

    /*
    |--------------------------------------------------------------------------
    | Event Workspace Counts
    |--------------------------------------------------------------------------
    */

    public function getCardTypesCountAttribute(): int
    {
        return $this->cardTypes()->count();
    }

    public function getInviteesCountAttribute(): int
    {
        return $this->invitees()->count();
    }

    public function getGeneratedCardsCountAttribute(): int
    {
        return $this->generatedCards()->count();
    }

    public function getCheckInsCountAttribute(): int
    {
        return $this->checkIns()->count();
    }

    public function getInviteeUploadsCountAttribute(): int
    {
        return $this->inviteeUploads()->count();
    }

    public function getPendingInviteeUploadsCountAttribute(): int
    {
        return $this->inviteeUploads()
            ->where('status', InviteeUpload::STATUS_PENDING)
            ->count();
    }

    public function getApprovedInviteeUploadsCountAttribute(): int
    {
        return $this->inviteeUploads()
            ->where('status', InviteeUpload::STATUS_APPROVED)
            ->count();
    }

    public function getRejectedInviteeUploadsCountAttribute(): int
    {
        return $this->inviteeUploads()
            ->where('status', InviteeUpload::STATUS_REJECTED)
            ->count();
    }

    public function getRsvpPendingCountAttribute(): int
    {
        return $this->invitees()
            ->where('rsvp_status', 'pending')
            ->count();
    }

    public function getRsvpAttendingCountAttribute(): int
    {
        return $this->invitees()
            ->where('rsvp_status', 'attending')
            ->count();
    }

    public function getRsvpNotAttendingCountAttribute(): int
    {
        return $this->invitees()
            ->where('rsvp_status', 'not_attending')
            ->count();
    }

    public function getCheckedInInviteesCountAttribute(): int
    {
        return $this->invitees()
            ->where(function ($query) {
                $query->where('check_in_status', 'checked_in')
                    ->orWhere('checked_in_count', '>', 0);
            })
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | Older SMS Log Counts
    |--------------------------------------------------------------------------
    */

    public function getSmsSentCountAttribute(): int
    {
        return $this->smsLogs()
            ->whereIn('status', ['sent', 'delivered', 'logged'])
            ->count();
    }

    public function getSmsFailedCountAttribute(): int
    {
        return $this->smsLogs()
            ->where('status', 'failed')
            ->count();
    }

    public function getSmsPendingCountAttribute(): int
    {
        return $this->smsLogs()
            ->whereIn('status', ['pending', 'queued', 'sending'])
            ->count();
    }

    public function getInvitationSmsSentCountAttribute(): int
    {
        return $this->smsLogs()
            ->whereIn('sms_type', ['invitation', 'invitation_card'])
            ->whereIn('status', ['sent', 'delivered', 'logged'])
            ->count();
    }

    public function getReminderSmsSentCountAttribute(): int
    {
        return $this->smsLogs()
            ->whereIn('sms_type', [
                'rsvp_pending_reminder',
                'attending_reminder',
            ])
            ->whereIn('status', ['sent', 'delivered', 'logged'])
            ->count();
    }

    public function getFinalSmsSentCountAttribute(): int
    {
        return $this->smsLogs()
            ->where('sms_type', 'event_day_reminder')
            ->whereIn('status', ['sent', 'delivered', 'logged'])
            ->count();
    }

    public function getWelcomeSmsSentCountAttribute(): int
    {
        return $this->smsLogs()
            ->whereIn('sms_type', ['welcome_checkin', 'welcome_sms', 'welcome'])
            ->whereIn('status', ['accepted', 'sent', 'delivered', 'logged'])
            ->count();
    }

    public function getWelcomeSmsFailedCountAttribute(): int
    {
        return $this->smsLogs()
            ->whereIn('sms_type', ['welcome_checkin', 'welcome_sms', 'welcome'])
            ->whereIn('status', ['failed', 'rejected'])
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | Unified Communication Counts - SMS + WhatsApp
    |--------------------------------------------------------------------------
    */

    public function getMessageLogCountAttribute(): int
    {
        return $this->messageLogs()->count();
    }

    public function getCommunicationLogsCountAttribute(): int
    {
        return $this->messageLogs()->count();
    }

    public function getCommunicationSentCountAttribute(): int
    {
        return $this->messageLogs()
            ->whereIn('status', ['sent', 'delivered', 'read', 'logged'])
            ->count();
    }

    public function getCommunicationLoggedCountAttribute(): int
    {
        return $this->messageLogs()
            ->where('status', 'logged')
            ->count();
    }

    public function getCommunicationPendingCountAttribute(): int
    {
        return $this->messageLogs()
            ->whereIn('status', ['pending', 'queued', 'sending'])
            ->count();
    }

    public function getCommunicationFailedCountAttribute(): int
    {
        return $this->messageLogs()
            ->where('status', 'failed')
            ->count();
    }

    public function getSmsMessageLogCountAttribute(): int
    {
        return $this->messageLogs()
            ->where('channel', 'sms')
            ->count();
    }

    public function getSmsMessageSentCountAttribute(): int
    {
        return $this->messageLogs()
            ->where('channel', 'sms')
            ->whereIn('status', ['sent', 'delivered', 'logged'])
            ->count();
    }

    public function getSmsMessageLoggedCountAttribute(): int
    {
        return $this->messageLogs()
            ->where('channel', 'sms')
            ->where('status', 'logged')
            ->count();
    }

    public function getSmsMessageFailedCountAttribute(): int
    {
        return $this->messageLogs()
            ->where('channel', 'sms')
            ->where('status', 'failed')
            ->count();
    }

    public function getWhatsappMessageLogCountAttribute(): int
    {
        return $this->messageLogs()
            ->where('channel', 'whatsapp')
            ->count();
    }

    public function getWhatsappSentCountAttribute(): int
    {
        return $this->messageLogs()
            ->where('channel', 'whatsapp')
            ->whereIn('status', ['sent', 'delivered', 'read'])
            ->count();
    }

    public function getWhatsappLoggedCountAttribute(): int
    {
        return $this->messageLogs()
            ->where('channel', 'whatsapp')
            ->where('status', 'logged')
            ->count();
    }

    public function getWhatsappFailedCountAttribute(): int
    {
        return $this->messageLogs()
            ->where('channel', 'whatsapp')
            ->where('status', 'failed')
            ->count();
    }

    public function getInvitationCardMessageCountAttribute(): int
    {
        return $this->messageLogs()
            ->whereIn('type', ['invitation_card', 'invitation'])
            ->count();
    }

    public function getInvitationCardMessageSentCountAttribute(): int
    {
        return $this->messageLogs()
            ->whereIn('type', ['invitation_card', 'invitation'])
            ->whereIn('status', ['sent', 'delivered', 'read', 'logged'])
            ->count();
    }

    public function getInvitationCardMessageFailedCountAttribute(): int
    {
        return $this->messageLogs()
            ->whereIn('type', ['invitation_card', 'invitation'])
            ->where('status', 'failed')
            ->count();
    }
}