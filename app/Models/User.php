<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_EVENT_OWNER = 'event_owner';
    public const ROLE_EVENT_MANAGER = 'event_manager';
    public const ROLE_CARD_DESIGNER = 'card_designer';
    public const ROLE_MESSAGE_SENDER = 'message_sender';
    public const ROLE_GATE_SCANNER = 'gate_scanner';
    public const ROLE_REPORT_VIEWER = 'report_viewer';

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

    public static function roles(): array
    {
        return [
            self::ROLE_SUPER_ADMIN => 'Super Admin',
            self::ROLE_EVENT_OWNER => 'Event Owner',
            self::ROLE_EVENT_MANAGER => 'Event Manager',
            self::ROLE_CARD_DESIGNER => 'Card Designer',
            self::ROLE_MESSAGE_SENDER => 'Message Sender',
            self::ROLE_GATE_SCANNER => 'Gate Scanner',
            self::ROLE_REPORT_VIEWER => 'Report Viewer',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_EVENT_OWNER,
            self::ROLE_EVENT_MANAGER,
            self::ROLE_CARD_DESIGNER,
            self::ROLE_MESSAGE_SENDER,
            self::ROLE_GATE_SCANNER,
            self::ROLE_REPORT_VIEWER,
        ]);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isEventOwner(): bool
    {
        return $this->role === self::ROLE_EVENT_OWNER;
    }

    public function isEventManager(): bool
    {
        return $this->role === self::ROLE_EVENT_MANAGER;
    }

    public function isCardDesigner(): bool
    {
        return $this->role === self::ROLE_CARD_DESIGNER;
    }

    public function isMessageSender(): bool
    {
        return $this->role === self::ROLE_MESSAGE_SENDER;
    }

    public function isGateScanner(): bool
    {
        return $this->role === self::ROLE_GATE_SCANNER;
    }

    public function isReportViewer(): bool
    {
        return $this->role === self::ROLE_REPORT_VIEWER;
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function canManageEvents(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_EVENT_OWNER,
            self::ROLE_EVENT_MANAGER,
        ]);
    }

    public function canManageCardDesigns(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_EVENT_OWNER,
            self::ROLE_EVENT_MANAGER,
            self::ROLE_CARD_DESIGNER,
        ]);
    }

    public function canSendMessages(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_EVENT_OWNER,
            self::ROLE_EVENT_MANAGER,
            self::ROLE_MESSAGE_SENDER,
        ]);
    }

    public function canScanGuests(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_EVENT_OWNER,
            self::ROLE_EVENT_MANAGER,
            self::ROLE_GATE_SCANNER,
        ]);
    }

    public function canViewReports(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_EVENT_OWNER,
            self::ROLE_EVENT_MANAGER,
            self::ROLE_REPORT_VIEWER,
        ]);
    }
}