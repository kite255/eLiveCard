<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_EVENT_ADMIN = 'event_admin';
    public const ROLE_CHECK_IN_OFFICER = 'check_in_officer';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Events created/owned by this user.
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Events assigned to this user through the event_user pivot table.
     *
     * Pivot fields:
     * - role: event_admin / check_in_officer
     * - is_active: true / false
     */
    public function assignedEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_user')
            ->withPivot([
                'role',
                'is_active',
            ])
            ->withTimestamps();
    }

    public function activeAssignedEvents(): BelongsToMany
    {
        return $this->assignedEvents()
            ->wherePivot('is_active', true);
    }

    public function assignedManagedEvents(): BelongsToMany
    {
        return $this->activeAssignedEvents()
            ->wherePivot('role', self::ROLE_EVENT_ADMIN);
    }

    public function assignedCheckInEvents(): BelongsToMany
    {
        return $this->activeAssignedEvents()
            ->wherePivot('role', self::ROLE_CHECK_IN_OFFICER);
    }

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    */

    public static function roles(): array
    {
        return [
            self::ROLE_SUPER_ADMIN => 'Super Admin',
            self::ROLE_EVENT_ADMIN => 'Event Admin',
            self::ROLE_CHECK_IN_OFFICER => 'Check-in Officer',
        ];
    }

    public static function defaultRole(): string
    {
        return self::ROLE_EVENT_ADMIN;
    }

    public function roleLabel(): string
    {
        return self::roles()[$this->role] ?? str((string) $this->role)
            ->replace('_', ' ')
            ->title()
            ->toString();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(array_keys(self::roles()));
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /*
    |--------------------------------------------------------------------------
    | Main Role Checks
    |--------------------------------------------------------------------------
    */

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN);
    }

    public function isEventAdmin(): bool
    {
        return $this->hasRole(self::ROLE_EVENT_ADMIN);
    }

    public function isCheckInOfficer(): bool
    {
        return $this->hasRole(self::ROLE_CHECK_IN_OFFICER);
    }

    public function isEventStaff(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_EVENT_ADMIN,
            self::ROLE_CHECK_IN_OFFICER,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Backward Compatibility Role Checks
    |--------------------------------------------------------------------------
    */

    public function isEventOwner(): bool
    {
        return $this->isEventAdmin();
    }

    public function isEventManager(): bool
    {
        return $this->isEventAdmin();
    }

    public function isCardDesigner(): bool
    {
        return $this->isEventAdmin();
    }

    public function isMessageSender(): bool
    {
        return $this->isEventAdmin();
    }

    public function isGateScanner(): bool
    {
        return $this->isCheckInOfficer();
    }

    public function isReportViewer(): bool
    {
        return $this->isEventAdmin();
    }

    /*
    |--------------------------------------------------------------------------
    | Permission Helpers
    |--------------------------------------------------------------------------
    */

    public function canManageUsers(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canManageSystemSettings(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canViewAuditLogs(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canManageAllEvents(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canManageEvents(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_EVENT_ADMIN,
        ]);
    }

    public function canManageInvitees(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_EVENT_ADMIN,
        ]);
    }

    public function canImportInvitees(): bool
    {
        return $this->canManageInvitees();
    }

    public function canManageCardTypes(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_EVENT_ADMIN,
        ]);
    }

    public function canManageCardDesigns(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_EVENT_ADMIN,
        ]);
    }

    public function canGenerateCards(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_EVENT_ADMIN,
        ]);
    }

    public function canSendMessages(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_EVENT_ADMIN,
        ]);
    }

    public function canManageRsvp(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_EVENT_ADMIN,
        ]);
    }

    public function canScanGuests(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_EVENT_ADMIN,
            self::ROLE_CHECK_IN_OFFICER,
        ]);
    }

    public function canViewReports(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_EVENT_ADMIN,
        ]);
    }

    public function canManageInviteeUploads(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_EVENT_ADMIN,
        ]);
    }

    public function canApproveInviteeUploads(): bool
    {
        return $this->canManageInviteeUploads();
    }

    /*
    |--------------------------------------------------------------------------
    | Event Assignment Helpers
    |--------------------------------------------------------------------------
    */

    public function isAssignedToEvent(Event|int $event, ?string $assignmentRole = null): bool
    {
        $eventId = $event instanceof Event ? $event->id : $event;

        if (! $eventId) {
            return false;
        }

        return $this->assignedEvents()
            ->where('events.id', $eventId)
            ->where('event_user.is_active', true)
            ->when($assignmentRole, function ($query) use ($assignmentRole): void {
                $query->where('event_user.role', $assignmentRole);
            })
            ->exists();
    }

    public function isAssignedAsEventAdmin(Event|int $event): bool
    {
        return $this->isAssignedToEvent($event, self::ROLE_EVENT_ADMIN);
    }

    public function isAssignedAsCheckInOfficer(Event|int $event): bool
    {
        return $this->isAssignedToEvent($event, self::ROLE_CHECK_IN_OFFICER);
    }

    /*
    |--------------------------------------------------------------------------
    | Event Access Helpers
    |--------------------------------------------------------------------------
    */

    public function ownsEvent(Event $event): bool
    {
        return (int) $event->user_id === (int) $this->id;
    }

    /**
     * General event visibility.
     *
     * super_admin:
     * - Can access all events.
     *
     * event_admin:
     * - Can access owned events.
     * - Can access events assigned to them in event_user.
     *
     * check_in_officer:
     * - Can access only events assigned to them for check-in.
     */
    public function canAccessEvent(Event $event): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->isEventAdmin()) {
            return $this->ownsEvent($event)
                || $this->isAssignedToEvent($event);
        }

        if ($this->isCheckInOfficer()) {
            return $this->isAssignedAsCheckInOfficer($event);
        }

        return false;
    }

    /**
     * Full management access to event admin features.
     */
    public function canManageEvent(Event $event): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! $this->isEventAdmin()) {
            return false;
        }

        return $this->ownsEvent($event)
            || $this->isAssignedAsEventAdmin($event);
    }

    /**
     * Gate/check-in access for a specific event.
     */
    public function canCheckInForEvent(Event $event): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->isEventAdmin()) {
            return $this->ownsEvent($event)
                || $this->isAssignedToEvent($event);
        }

        if ($this->isCheckInOfficer()) {
            return $this->isAssignedAsCheckInOfficer($event);
        }

        return false;
    }

    /**
     * Report access for a specific event.
     */
    public function canViewEventReports(Event $event): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! $this->isEventAdmin()) {
            return false;
        }

        return $this->ownsEvent($event)
            || $this->isAssignedAsEventAdmin($event);
    }
}
