<?php

namespace App\Models;

use App\Support\EliveMessagePlaceholders;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
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
        'is_public',

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
        'is_public' => 'boolean',

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

    /** Display-only status. It is calculated and is not stored in the database. */
    public const STATUS_UPCOMING = 'upcoming';

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

    public static function eventAssignmentRoles(): array
    {
        return [
            'event_admin' => 'Event Admin',
            'check_in_officer' => 'Check-in Officer',
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

    public function auditLogs(): HasMany
    {
        return $this->hasMany(
            AuditLog::class,
            'event_id'
        );
    }

    public function recentAuditLogs(): HasMany
    {
        return $this->auditLogs()
            ->where(
                'created_at',
                '>=',
                now()->subDays(7)
            );
    }

    public function systemAuditLogs(): HasMany
    {
        return $this->auditLogs()
            ->whereNull('user_id');
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_user')
            ->withPivot([
                'role',
                'is_active',
            ])
            ->withTimestamps();
    }

    public function activeAssignedUsers(): BelongsToMany
    {
        return $this->assignedUsers()
            ->wherePivot('is_active', true);
    }

    public function checkInOfficers(): BelongsToMany
    {
        return $this->assignedUsers()
            ->wherePivot('role', 'check_in_officer')
            ->wherePivot('is_active', true);
    }

    public function eventAdmins(): BelongsToMany
    {
        return $this->assignedUsers()
            ->wherePivot('role', 'event_admin')
            ->wherePivot('is_active', true);
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

    /**
     * Events happening right now.
     *
     * A stored "active" event is only displayed as Active when the current
     * date and time fall between its start and end boundaries.
     */
    public function scopeActive(Builder $query): Builder
    {
        $today = now()->toDateString();
        $currentTime = now()->format('H:i:s');

        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->whereDate('event_date', $today)
            ->where(function (Builder $query) use ($currentTime): void {
                $query
                    ->whereNull('start_time')
                    ->orWhereTime('start_time', '<=', $currentTime);
            })
            ->where(function (Builder $query) use ($currentTime): void {
                $query
                    ->whereNull('end_time')
                    ->orWhereTime('end_time', '>=', $currentTime);
            });
    }

    /**
     * Events activated by an administrator but whose start time has not arrived.
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        $today = now()->toDateString();
        $currentTime = now()->format('H:i:s');

        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->where(function (Builder $query) use ($today, $currentTime): void {
                $query
                    ->whereDate('event_date', '>', $today)
                    ->orWhere(function (Builder $query) use ($today, $currentTime): void {
                        $query
                            ->whereDate('event_date', $today)
                            ->whereNotNull('start_time')
                            ->whereTime('start_time', '>', $currentTime);
                    });
            });
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Events explicitly completed or active events whose end boundary passed.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        $today = now()->toDateString();
        $currentTime = now()->format('H:i:s');

        return $query->where(function (Builder $query) use ($today, $currentTime): void {
            $query
                ->where('status', self::STATUS_COMPLETED)
                ->orWhere(function (Builder $query) use ($today, $currentTime): void {
                    $query
                        ->where('status', self::STATUS_ACTIVE)
                        ->where(function (Builder $query) use ($today, $currentTime): void {
                            $query
                                ->whereDate('event_date', '<', $today)
                                ->orWhere(function (Builder $query) use ($today, $currentTime): void {
                                    $query
                                        ->whereDate('event_date', $today)
                                        ->whereNotNull('end_time')
                                        ->whereTime('end_time', '<', $currentTime);
                                });
                        });
                });
        });
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    /**
     * Apply any calculated display-status filter consistently.
     */
    public function scopeWithDisplayStatus(Builder $query, ?string $status): Builder
    {
        return match ($status) {
            self::STATUS_ACTIVE => $query->active(),
            self::STATUS_UPCOMING => $query->upcoming(),
            self::STATUS_COMPLETED => $query->completed(),
            self::STATUS_DRAFT => $query->draft(),
            self::STATUS_CANCELLED => $query->cancelled(),
            default => $query,
        };
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('is_public', true)
            ->whereNotIn('status', [
                self::STATUS_DRAFT,
                self::STATUS_CANCELLED,
            ]);
    }

    public function scopeUpcomingPublic(Builder $query): Builder
    {
        return $query
            ->publiclyVisible()
            ->upcoming()
            ->orderBy('event_date')
            ->orderBy('start_time');
    }

    public function scopeActivePublic(Builder $query): Builder
    {
        return $query
            ->publiclyVisible()
            ->active()
            ->orderBy('event_date')
            ->orderBy('start_time');
    }

    public function scopePastPublic(Builder $query): Builder
    {
        return $query
            ->publiclyVisible()
            ->completed()
            ->orderByDesc('event_date')
            ->orderByDesc('end_time')
            ->orderByDesc('start_time');
    }

    public function scopeAutomaticSmsEnabled(Builder $query): Builder
    {
        return $query->where(
            'auto_sms_reminders_enabled',
            true
        );
    }

    public function scopeWelcomeSmsEnabled(Builder $query): Builder
    {
        return $query->where(
            'welcome_sms_enabled',
            true
        );
    }

    public function scopeOwnedBy(Builder $query, User|int|null $user): Builder
    {
        $userId = $user instanceof User ? $user->id : $user;

        if (! $userId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('user_id', $userId);
    }

    public function scopeVisibleTo(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->where(function ($query) use ($user): void {
            $query
                ->where('user_id', $user->id)
                ->orWhereHas('assignedUsers', function ($query) use ($user): void {
                    $query
                        ->where('users.id', $user->id)
                        ->where('event_user.is_active', true);
                });
        });
    }

    public function scopeCheckInVisibleTo(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->where(function ($query) use ($user): void {
            $query
                ->where('user_id', $user->id)
                ->orWhereHas('assignedUsers', function ($query) use ($user): void {
                    $query
                        ->where('users.id', $user->id)
                        ->where('event_user.is_active', true)
                        ->whereIn('event_user.role', [
                            'event_admin',
                            'check_in_officer',
                        ]);
                });
        });
    }

    public function isAssignedTo(User|int|null $user, ?string $role = null): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        if (! $userId) {
            return false;
        }

        return $this->assignedUsers()
            ->where('users.id', $userId)
            ->where('event_user.is_active', true)
            ->when($role, fn ($query) => $query->where('event_user.role', $role))
            ->exists();
    }

    public function canBeManagedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($this->user_id === $user->id) {
            return true;
        }

        return $this->isAssignedTo($user, 'event_admin');
    }

    public function canViewAuditLogsBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $this->canBeManagedBy($user)) {
            return false;
        }

        return method_exists($user, 'canViewReports')
            ? (bool) $user->canViewReports()
            : true;
    }

    public function canBeCheckedInBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($this->user_id === $user->id) {
            return true;
        }

        return $this->assignedUsers()
            ->where('users.id', $user->id)
            ->where('event_user.is_active', true)
            ->whereIn('event_user.role', [
                'event_admin',
                'check_in_officer',
            ])
            ->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | Status Helpers
    |--------------------------------------------------------------------------
    */

    public function getStartsAtAttribute(): ?Carbon
    {
        if (! $this->event_date) {
            return null;
        }

        $startsAt = $this->event_date->copy()->startOfDay();

        if ($this->start_time) {
            $startsAt->setTime(
                $this->start_time->hour,
                $this->start_time->minute,
                $this->start_time->second
            );
        }

        return $startsAt;
    }

    public function getEndsAtAttribute(): ?Carbon
    {
        if (! $this->event_date) {
            return null;
        }

        $endsAt = $this->event_date->copy()->endOfDay();

        if ($this->end_time) {
            $endsAt->setTime(
                $this->end_time->hour,
                $this->end_time->minute,
                $this->end_time->second
            );
        }

        return $endsAt;
    }

    public function getDisplayStatusAttribute(): string
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return self::STATUS_CANCELLED;
        }

        if ($this->status === self::STATUS_DRAFT) {
            return self::STATUS_DRAFT;
        }

        if ($this->status === self::STATUS_COMPLETED) {
            return self::STATUS_COMPLETED;
        }

        if (! $this->starts_at || ! $this->ends_at) {
            return $this->status ?: self::STATUS_DRAFT;
        }

        $now = now();

        if ($now->greaterThan($this->ends_at)) {
            return self::STATUS_COMPLETED;
        }

        if ($now->betweenIncluded($this->starts_at, $this->ends_at)) {
            return self::STATUS_ACTIVE;
        }

        return self::STATUS_UPCOMING;
    }

    public function getDisplayStatusLabelAttribute(): string
    {
        return match ($this->display_status) {
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_UPCOMING => 'Upcoming',
            default => ucfirst(str_replace('_', ' ', (string) $this->display_status)),
        };
    }

    public function getDisplayStatusClassesAttribute(): string
    {
        return match ($this->display_status) {
            self::STATUS_ACTIVE =>
                'border border-emerald-200 bg-emerald-50 text-emerald-700',

            self::STATUS_UPCOMING =>
                'border border-orange-200 bg-orange-50 text-orange-700',

            self::STATUS_COMPLETED =>
                'border border-blue-200 bg-blue-50 text-blue-700',

            self::STATUS_DRAFT =>
                'border border-slate-200 bg-slate-100 text-slate-600',

            self::STATUS_CANCELLED =>
                'border border-red-200 bg-red-50 text-red-700',

            default =>
                'border border-slate-200 bg-slate-50 text-slate-600',
        };
    }

    public function getDisplayStatusDotClassesAttribute(): string
    {
        return match ($this->display_status) {
            self::STATUS_ACTIVE => 'bg-emerald-500',
            self::STATUS_UPCOMING => 'bg-[#FD9618]',
            self::STATUS_COMPLETED => 'bg-[#213B73]',
            self::STATUS_DRAFT => 'bg-slate-400',
            self::STATUS_CANCELLED => 'bg-red-500',
            default => 'bg-slate-400',
        };
    }

    public function getDisplayStatusIconAttribute(): string
    {
        return match ($this->display_status) {
            self::STATUS_ACTIVE => 'heroicon-o-bolt',
            self::STATUS_UPCOMING => 'heroicon-o-clock',
            self::STATUS_COMPLETED => 'heroicon-o-check-circle',
            self::STATUS_DRAFT => 'heroicon-o-pencil-square',
            self::STATUS_CANCELLED => 'heroicon-o-x-circle',
            default => 'heroicon-o-information-circle',
        };
    }

    public function isDraft(): bool
    {
        return $this->display_status === self::STATUS_DRAFT;
    }

    public function isActive(): bool
    {
        return $this->display_status === self::STATUS_ACTIVE;
    }

    public function isCompleted(): bool
    {
        return $this->display_status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->display_status === self::STATUS_CANCELLED;
    }

    public function isPublic(): bool
    {
        return (bool) $this->is_public;
    }

    public function canBeShownPublicly(): bool
    {
        return $this->isPublic()
            && ! $this->isDraft()
            && ! $this->isCancelled();
    }

    public function isUpcoming(): bool
    {
        return $this->display_status === self::STATUS_UPCOMING;
    }

    public function isPast(): bool
    {
        return $this->display_status === self::STATUS_COMPLETED;
    }

    public function isHappeningNow(): bool
    {
        return $this->display_status === self::STATUS_ACTIVE;
    }

    public function getPublicStatusLabelAttribute(): string
    {
        return match ($this->display_status) {
            self::STATUS_ACTIVE => 'Happening Now',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_UPCOMING => $this->event_date?->isToday() ? 'Today' : 'Upcoming',
            default => 'Upcoming',
        };
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
        $coverImage = $this->cover_image;

        if (is_array($coverImage)) {
            $coverImage = collect($coverImage)->first();
        }

        if (! filled($coverImage)) {
            return null;
        }

        $coverImage = trim((string) $coverImage);

        if (filter_var($coverImage, FILTER_VALIDATE_URL)) {
            return $coverImage;
        }

        $coverImage = ltrim($coverImage, '/');

        foreach (['storage/', 'public/'] as $prefix) {
            if (str_starts_with($coverImage, $prefix)) {
                $coverImage = substr($coverImage, strlen($prefix));
            }
        }

        return Storage::disk('public')->exists($coverImage)
            ? Storage::disk('public')->url($coverImage)
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

    public function getPublicSummaryAttribute(): ?string
    {
        return filled($this->welcome_message)
            ? str($this->welcome_message)->stripTags()->squish()->limit(180)->toString()
            : null;
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
        return $this->display_status_label;
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
            ->where('status', 'pending')
            ->count();
    }

    public function getApprovedInviteeUploadsCountAttribute(): int
    {
        return $this->inviteeUploads()
            ->where('status', 'approved')
            ->count();
    }

    public function getRejectedInviteeUploadsCountAttribute(): int
    {
        return $this->inviteeUploads()
            ->where('status', 'rejected')
            ->count();
    }

    public function getAuditLogsCountAttribute(): int
    {
        return $this->auditLogs()->count();
    }

    public function getRecentAuditLogsCountAttribute(): int
    {
        return $this->recentAuditLogs()->count();
    }

    public function getSystemAuditLogsCountAttribute(): int
    {
        return $this->systemAuditLogs()->count();
    }

    public function getRsvpPendingCountAttribute(): int
    {
        return $this->invitees()
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('rsvp_status')
                    ->orWhere('rsvp_status', '')
                    ->orWhere('rsvp_status', 'pending')
                    ->orWhere('rsvp_status', 'maybe');
            })
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
            ->whereIn('status', [
                'accepted',
                'sent',
                'delivered',
                'read',
                'replied',
                'received',
                'logged',
            ])
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
            ->whereIn('status', [
                'failed',
                'rejected',
                'undelivered',
                'expired',
            ])
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
            ->whereIn('status', [
                'failed',
                'rejected',
                'undelivered',
                'expired',
            ])
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
            ->whereIn('status', [
                'failed',
                'rejected',
                'undelivered',
                'expired',
            ])
            ->count();
    }

    public function getInvitationCardMessageCountAttribute(): int
    {
        return $this->messageLogs()
            ->whereIn('type', [
                'invitation_card',
                'invitation',
                'whatsapp_invitation_card',
            ])
            ->count();
    }

    public function getInvitationCardMessageSentCountAttribute(): int
    {
        return $this->messageLogs()
            ->whereIn('type', [
                'invitation_card',
                'invitation',
                'whatsapp_invitation_card',
            ])
            ->whereIn('status', ['sent', 'delivered', 'read', 'logged'])
            ->count();
    }

    public function getInvitationCardMessageFailedCountAttribute(): int
    {
        return $this->messageLogs()
            ->whereIn('type', [
                'invitation_card',
                'invitation',
                'whatsapp_invitation_card',
            ])
            ->where('status', 'failed')
            ->count();
    }
}