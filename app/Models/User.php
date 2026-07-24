<?php

namespace App\Models;

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

    /*
    |--------------------------------------------------------------------------
    | System Roles
    |--------------------------------------------------------------------------
    */

    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_EVENT_ADMIN = 'event_admin';
    public const ROLE_CHECK_IN_OFFICER = 'check_in_officer';

    /*
    |--------------------------------------------------------------------------
    | Mass Assignment
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    /*
    |--------------------------------------------------------------------------
    | Hidden Attributes
    |--------------------------------------------------------------------------
    */

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /*
    |--------------------------------------------------------------------------
    | Attribute Casts
    |--------------------------------------------------------------------------
    */

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Events created or owned by this user.
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Events assigned to this user through the event_user pivot table.
     *
     * Pivot columns:
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

    /**
     * All active event assignments for this user.
     */
    public function activeAssignedEvents(): BelongsToMany
    {
        return $this->assignedEvents()
            ->where('event_user.is_active', true);
    }

    /**
     * Events assigned to this user as an Event Manager.
     */
    public function assignedManagedEvents(): BelongsToMany
    {
        return $this->assignedEvents()
            ->where('event_user.is_active', true)
            ->where(
                'event_user.role',
                self::ROLE_EVENT_ADMIN
            );
    }

    /**
     * Events assigned to this user as a Check-in Officer.
     */
    public function assignedCheckInEvents(): BelongsToMany
    {
        return $this->assignedEvents()
            ->where('event_user.is_active', true)
            ->where(
                'event_user.role',
                self::ROLE_CHECK_IN_OFFICER
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Role Configuration
    |--------------------------------------------------------------------------
    */

    public static function roles(): array
    {
        return [
            self::ROLE_SUPER_ADMIN => 'Super Admin',
            self::ROLE_EVENT_ADMIN => 'Event Manager',
            self::ROLE_CHECK_IN_OFFICER => 'Check-in Officer',
        ];
    }

    public static function defaultRole(): string
    {
        return self::ROLE_EVENT_ADMIN;
    }

    public function roleLabel(): string
    {
        return self::roles()[$this->role]
            ?? str((string) $this->role)
                ->replace('_', ' ')
                ->title()
                ->toString();
    }

    /*
    |--------------------------------------------------------------------------
    | Filament Access
    |--------------------------------------------------------------------------
    */

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active
            && $this->hasAnyRole(array_keys(self::roles()));
    }

    /*
    |--------------------------------------------------------------------------
    | General Role Helpers
    |--------------------------------------------------------------------------
    */

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
    | Global Permission Helpers
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
        return $this->canManageEvents();
    }

    public function canImportInvitees(): bool
    {
        return $this->canManageInvitees();
    }

    public function canManageCardTypes(): bool
    {
        return $this->canManageEvents();
    }

    public function canManageCardDesigns(): bool
    {
        return $this->canManageEvents();
    }

    public function canGenerateCards(): bool
    {
        return $this->canManageEvents();
    }

    public function canSendMessages(): bool
    {
        return $this->canManageEvents();
    }

    public function canManageRsvp(): bool
    {
        return $this->canManageEvents();
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
        return $this->canManageEvents();
    }

    public function canManageInviteeUploads(): bool
    {
        return $this->canManageEvents();
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

    public function isAssignedToEvent(
        Event|int $event,
        ?string $assignmentRole = null
    ): bool {
        $eventId = $event instanceof Event
            ? $event->getKey()
            : $event;

        if (! $eventId) {
            return false;
        }

        $query = $this->assignedEvents()
            ->where('events.id', $eventId)
            ->where('event_user.is_active', true);

        if ($assignmentRole !== null) {
            $query->where(
                'event_user.role',
                $assignmentRole
            );
        }

        return $query->exists();
    }

    public function isAssignedAsEventAdmin(Event|int $event): bool
    {
        return $this->isAssignedToEvent(
            $event,
            self::ROLE_EVENT_ADMIN
        );
    }

    public function isAssignedAsCheckInOfficer(Event|int $event): bool
    {
        return $this->isAssignedToEvent(
            $event,
            self::ROLE_CHECK_IN_OFFICER
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Event Access Helpers
    |--------------------------------------------------------------------------
    */

    public function ownsEvent(Event $event): bool
    {
        return (int) $event->user_id === (int) $this->getKey();
    }

    /**
     * Determine whether the user may view an event.
     */
    public function canAccessEvent(Event $event): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->isEventAdmin()) {
            return $this->ownsEvent($event)
                || $this->isAssignedAsEventAdmin($event);
        }

        if ($this->isCheckInOfficer()) {
            return $this->isAssignedAsCheckInOfficer($event);
        }

        return false;
    }

    /**
     * Determine whether the user may use full event-management features.
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
     * Determine whether the user may perform check-in for an event.
     */
    public function canCheckInForEvent(Event $event): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->isEventAdmin()) {
            return $this->ownsEvent($event)
                || $this->isAssignedAsEventAdmin($event);
        }

        if ($this->isCheckInOfficer()) {
            return $this->isAssignedAsCheckInOfficer($event);
        }

        return false;
    }

    /**
     * Determine whether the user may view reports for an event.
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
