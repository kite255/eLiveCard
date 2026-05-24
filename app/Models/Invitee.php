<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class Invitee extends Model
{
    public const CARD_STATUS_PENDING = 'pending';
    public const CARD_STATUS_ACTIVE = 'active';
    public const CARD_STATUS_CANCELLED = 'cancelled';
    public const CARD_STATUS_BLOCKED = 'blocked';
    public const CARD_STATUS_USED = 'used';

    /*
    |--------------------------------------------------------------------------
    | RSVP Statuses
    |--------------------------------------------------------------------------
    | Keep both naming styles to avoid breaking old code and new RSVP controller.
    */
    public const RSVP_PENDING = 'pending';
    public const RSVP_ATTENDING = 'attending';
    public const RSVP_NOT_ATTENDING = 'not_attending';
    public const RSVP_MAYBE = 'maybe';

    public const RSVP_STATUS_PENDING = self::RSVP_PENDING;
    public const RSVP_STATUS_ATTENDING = self::RSVP_ATTENDING;
    public const RSVP_STATUS_NOT_ATTENDING = self::RSVP_NOT_ATTENDING;
    public const RSVP_STATUS_MAYBE = self::RSVP_MAYBE;

    public const SMS_STATUS_NOT_SENT = 'not_sent';
    public const SMS_STATUS_PENDING = 'pending';
    public const SMS_STATUS_SENT = 'sent';
    public const SMS_STATUS_DELIVERED = 'delivered';
    public const SMS_STATUS_FAILED = 'failed';

    public const QR_SIZE = 600;
    public const QR_MARGIN = 2;
    public const QR_ERROR_CORRECTION = 'H';
    public const QR_FOREGROUND_COLOR = '#000000';
    public const QR_BACKGROUND_COLOR = '#FFFFFF';

    protected $fillable = [
        'event_id',
        'card_type_id',
        'name',
        'phone',
        'email',
        'category',
        'table_number',
        'allowed_guests',
        'serial_number',
        'short_code',
        'qr_token',
        'qr_token_hash',
        'qr_code',
        'qr_code_path',
        'card_status',

        // RSVP fields
        'rsvp_status',
        'confirmed_guests',
        'rsvp_confirmed_at',
        'rsvp_token',

        // Check-in fields
        'checked_in_count',
        'checked_in_at',

        // Original SMS sending fields
        'sms_status',
        'sms_sent_at',
        'sms_message_id',
        'sms_error',

        // Reminder SMS tracking fields
        'invitation_sms_status',
        'invitation_sms_sent_at',
        'reminder_sms_status',
        'reminder_sms_sent_at',
        'final_sms_status',
        'final_sms_sent_at',
        'last_sms_error',
    ];

    protected $casts = [
        'allowed_guests' => 'integer',
        'confirmed_guests' => 'integer',
        'rsvp_confirmed_at' => 'datetime',

        'checked_in_at' => 'datetime',
        'checked_in_count' => 'integer',

        'sms_sent_at' => 'datetime',
        'invitation_sms_sent_at' => 'datetime',
        'reminder_sms_sent_at' => 'datetime',
        'final_sms_sent_at' => 'datetime',
    ];

    protected $appends = [
        'final_allowed_guests',
        'remaining_guests',
        'qr_code_url',
        'private_invitation_url',
        'rsvp_url',
    ];

    protected static function booted(): void
    {
        static::creating(function (Invitee $invitee) {
            if (blank($invitee->serial_number)) {
                $invitee->serial_number = self::generateUniqueSerialNumber();
            }

            if (blank($invitee->short_code)) {
                $invitee->short_code = self::generateUniqueShortCode();
            }

            if (blank($invitee->qr_token)) {
                $invitee->qr_token = self::generateUniqueQrToken();
            }

            if (blank($invitee->qr_token_hash)) {
                $invitee->qr_token_hash = hash('sha256', $invitee->qr_token);
            }

            if (blank($invitee->rsvp_token)) {
                $invitee->rsvp_token = self::generateUniqueRsvpToken();
            }

            if (blank($invitee->card_status)) {
                $invitee->card_status = self::CARD_STATUS_ACTIVE;
            }

            if (blank($invitee->rsvp_status)) {
                $invitee->rsvp_status = self::RSVP_PENDING;
            }

            if (blank($invitee->sms_status)) {
                $invitee->sms_status = self::SMS_STATUS_NOT_SENT;
            }

            if (blank($invitee->invitation_sms_status)) {
                $invitee->invitation_sms_status = self::SMS_STATUS_PENDING;
            }

            if (blank($invitee->reminder_sms_status)) {
                $invitee->reminder_sms_status = self::SMS_STATUS_PENDING;
            }

            if (blank($invitee->final_sms_status)) {
                $invitee->final_sms_status = self::SMS_STATUS_PENDING;
            }

            if ($invitee->checked_in_count === null) {
                $invitee->checked_in_count = 0;
            }
        });

        static::created(function (Invitee $invitee) {
            $invitee->generateQrCode();
        });
    }

    public static function generateUniqueSerialNumber(): string
    {
        do {
            $serialNumber = 'ELV-' . now()->format('Y') . '-' . strtoupper(Str::random(6));
        } while (self::query()->where('serial_number', $serialNumber)->exists());

        return $serialNumber;
    }

    public static function generateUniqueShortCode(): string
    {
        do {
            $shortCode = strtoupper(Str::random(6));
        } while (self::query()->where('short_code', $shortCode)->exists());

        return $shortCode;
    }

    public static function generateUniqueQrToken(): string
    {
        do {
            $token = Str::random(64);
        } while (self::query()->where('qr_token', $token)->exists());

        return $token;
    }

    public static function generateUniqueRsvpToken(): string
    {
        do {
            $token = Str::random(48);
        } while (self::query()->where('rsvp_token', $token)->exists());

        return $token;
    }

    public static function rsvpStatuses(): array
    {
        return [
            self::RSVP_PENDING => 'Pending',
            self::RSVP_ATTENDING => 'Attending',
            self::RSVP_NOT_ATTENDING => 'Not Attending',
            self::RSVP_MAYBE => 'Maybe',
        ];
    }

    public function rsvpUrl(): string
    {
        if (blank($this->rsvp_token)) {
            $this->forceFill([
                'rsvp_token' => self::generateUniqueRsvpToken(),
            ])->saveQuietly();

            $this->refresh();
        }

        if (Route::has('rsvp.show')) {
            return route('rsvp.show', $this->rsvp_token);
        }

        return url('/rsvp/' . $this->rsvp_token);
    }

    public function getRsvpUrlAttribute(): string
    {
        return $this->rsvpUrl();
    }

    public function isRsvpPending(): bool
    {
        return $this->rsvp_status === self::RSVP_PENDING;
    }

    public function isAttending(): bool
    {
        return $this->rsvp_status === self::RSVP_ATTENDING;
    }

    public function isNotAttending(): bool
    {
        return $this->rsvp_status === self::RSVP_NOT_ATTENDING;
    }

    public function markRsvpAsAttending(int $confirmedGuests = 1): void
    {
        $confirmedGuests = max(1, min($confirmedGuests, $this->final_allowed_guests));

        $this->forceFill([
            'rsvp_status' => self::RSVP_ATTENDING,
            'confirmed_guests' => $confirmedGuests,
            'rsvp_confirmed_at' => now(),
        ])->saveQuietly();
    }

    public function markRsvpAsNotAttending(): void
    {
        $this->forceFill([
            'rsvp_status' => self::RSVP_NOT_ATTENDING,
            'confirmed_guests' => 0,
            'rsvp_confirmed_at' => now(),
        ])->saveQuietly();
    }

    public function generateQrCode(): void
    {
        $this->ensureQrIdentityExists();

        $qrUrl = $this->getQrTargetUrl();

        $folder = 'events/' . $this->event_id . '/qr-codes';
        $fileName = $this->serial_number . '.png';
        $path = $folder . '/' . $fileName;

        [$foregroundRed, $foregroundGreen, $foregroundBlue] = $this->hexToRgb(self::QR_FOREGROUND_COLOR);
        [$backgroundRed, $backgroundGreen, $backgroundBlue] = $this->hexToRgb(self::QR_BACKGROUND_COLOR);

        $qrPng = QrCode::format('png')
            ->size(self::QR_SIZE)
            ->margin(self::QR_MARGIN)
            ->errorCorrection(self::QR_ERROR_CORRECTION)
            ->color($foregroundRed, $foregroundGreen, $foregroundBlue)
            ->backgroundColor($backgroundRed, $backgroundGreen, $backgroundBlue)
            ->generate($qrUrl);

        Storage::disk('public')->put($path, $qrPng);

        $data = [];

        if (Schema::hasColumn('invitees', 'qr_code')) {
            $data['qr_code'] = $path;
        }

        if (Schema::hasColumn('invitees', 'qr_code_path')) {
            $data['qr_code_path'] = $path;
        }

        if (! empty($data)) {
            $this->forceFill($data)->saveQuietly();
        }
    }

    public function regenerateQrCode(): void
    {
        $oldPaths = array_filter([
            $this->qr_code ?? null,
            $this->qr_code_path ?? null,
            'events/' . $this->event_id . '/qr-codes/' . $this->serial_number . '.svg',
            'events/' . $this->event_id . '/qr-codes/' . $this->serial_number . '.png',
        ]);

        foreach (array_unique($oldPaths) as $oldPath) {
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $this->generateQrCode();
    }

    protected function ensureQrIdentityExists(): void
    {
        $data = [];

        if (blank($this->serial_number)) {
            $data['serial_number'] = self::generateUniqueSerialNumber();
        }

        if (blank($this->short_code)) {
            $data['short_code'] = self::generateUniqueShortCode();
        }

        if (blank($this->qr_token)) {
            $data['qr_token'] = self::generateUniqueQrToken();
        }

        if (blank($this->rsvp_token)) {
            $data['rsvp_token'] = self::generateUniqueRsvpToken();
        }

        if (! empty($data)) {
            if (isset($data['qr_token'])) {
                $data['qr_token_hash'] = hash('sha256', $data['qr_token']);
            }

            $this->forceFill($data)->saveQuietly();
            $this->refresh();
        }

        if (blank($this->qr_token_hash) && filled($this->qr_token)) {
            $this->forceFill([
                'qr_token_hash' => hash('sha256', $this->qr_token),
            ])->saveQuietly();

            $this->refresh();
        }
    }

    public function getQrTargetUrl(): string
    {
        if (blank($this->short_code)) {
            $this->forceFill([
                'short_code' => self::generateUniqueShortCode(),
            ])->saveQuietly();

            $this->refresh();
        }

        if (Route::has('invitee.page')) {
            return route('invitee.page', $this->short_code);
        }

        return url('/i/' . $this->short_code);
    }

    public function getPrivateInvitationUrlAttribute(): string
    {
        return $this->getQrTargetUrl();
    }

    public function getQrCodeUrlAttribute(): ?string
    {
        $path = $this->qr_code_path ?? $this->qr_code ?? null;

        if (blank($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    protected function hexToRgb(?string $hex): array
    {
        $hex = $hex ?: '#000000';
        $hex = ltrim(trim($hex), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0]
                . $hex[1] . $hex[1]
                . $hex[2] . $hex[2];
        }

        if (! preg_match('/^[a-fA-F0-9]{6}$/', $hex)) {
            return [0, 0, 0];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    public function markSmsAsSent(?string $messageId = null): void
    {
        $this->forceFill([
            'sms_status' => self::SMS_STATUS_SENT,
            'sms_sent_at' => now(),
            'sms_message_id' => $messageId,
            'sms_error' => null,
        ])->saveQuietly();
    }

    public function markSmsAsFailed(string $error): void
    {
        $this->forceFill([
            'sms_status' => self::SMS_STATUS_FAILED,
            'sms_error' => $error,
        ])->saveQuietly();
    }

    public function markInvitationSmsAsSent(?string $messageId = null): void
    {
        $this->forceFill([
            'sms_status' => self::SMS_STATUS_SENT,
            'sms_sent_at' => now(),
            'sms_message_id' => $messageId,
            'sms_error' => null,

            'invitation_sms_status' => self::SMS_STATUS_SENT,
            'invitation_sms_sent_at' => now(),
            'last_sms_error' => null,
        ])->saveQuietly();
    }

    public function markInvitationSmsAsFailed(string $error): void
    {
        $this->forceFill([
            'sms_status' => self::SMS_STATUS_FAILED,
            'sms_error' => $error,

            'invitation_sms_status' => self::SMS_STATUS_FAILED,
            'last_sms_error' => $error,
        ])->saveQuietly();
    }

    public function markReminderSmsAsSent(?string $messageId = null): void
    {
        $this->forceFill([
            'sms_status' => self::SMS_STATUS_SENT,
            'sms_sent_at' => now(),
            'sms_message_id' => $messageId,
            'sms_error' => null,

            'reminder_sms_status' => self::SMS_STATUS_SENT,
            'reminder_sms_sent_at' => now(),
            'last_sms_error' => null,
        ])->saveQuietly();
    }

    public function markReminderSmsAsFailed(string $error): void
    {
        $this->forceFill([
            'sms_status' => self::SMS_STATUS_FAILED,
            'sms_error' => $error,

            'reminder_sms_status' => self::SMS_STATUS_FAILED,
            'last_sms_error' => $error,
        ])->saveQuietly();
    }

    public function markFinalSmsAsSent(?string $messageId = null): void
    {
        $this->forceFill([
            'sms_status' => self::SMS_STATUS_SENT,
            'sms_sent_at' => now(),
            'sms_message_id' => $messageId,
            'sms_error' => null,

            'final_sms_status' => self::SMS_STATUS_SENT,
            'final_sms_sent_at' => now(),
            'last_sms_error' => null,
        ])->saveQuietly();
    }

    public function markFinalSmsAsFailed(string $error): void
    {
        $this->forceFill([
            'sms_status' => self::SMS_STATUS_FAILED,
            'sms_error' => $error,

            'final_sms_status' => self::SMS_STATUS_FAILED,
            'last_sms_error' => $error,
        ])->saveQuietly();
    }

    public function updateSmsStatusByType(
        string $smsType,
        string $status,
        ?string $messageId = null,
        ?string $error = null
    ): void {
        match ($smsType) {
            SmsLog::TYPE_INVITATION => $status === self::SMS_STATUS_SENT
                ? $this->markInvitationSmsAsSent($messageId)
                : $this->markInvitationSmsAsFailed($error ?? 'SMS failed'),

            SmsLog::TYPE_RSVP_PENDING_REMINDER,
            SmsLog::TYPE_ATTENDING_REMINDER => $status === self::SMS_STATUS_SENT
                ? $this->markReminderSmsAsSent($messageId)
                : $this->markReminderSmsAsFailed($error ?? 'SMS failed'),

            SmsLog::TYPE_EVENT_DAY_REMINDER => $status === self::SMS_STATUS_SENT
                ? $this->markFinalSmsAsSent($messageId)
                : $this->markFinalSmsAsFailed($error ?? 'SMS failed'),

            default => $status === self::SMS_STATUS_SENT
                ? $this->markSmsAsSent($messageId)
                : $this->markSmsAsFailed($error ?? 'SMS failed'),
        };
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function cardType(): BelongsTo
    {
        return $this->belongsTo(CardType::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    public function smsLogs(): HasMany
    {
        return $this->hasMany(SmsLog::class);
    }

    public function getFinalAllowedGuestsAttribute(): int
    {
        return $this->allowed_guests
            ?? $this->cardType?->allowed_people
            ?? $this->cardType?->allowed_guests
            ?? $this->cardType?->guest_count
            ?? 1;
    }

    public function getRemainingGuestsAttribute(): int
    {
        return max(0, $this->final_allowed_guests - $this->checked_in_count);
    }

    public function isActive(): bool
    {
        return $this->card_status === self::CARD_STATUS_ACTIVE;
    }

    public function isPending(): bool
    {
        return $this->card_status === self::CARD_STATUS_PENDING;
    }

    public function isCancelled(): bool
    {
        return $this->card_status === self::CARD_STATUS_CANCELLED;
    }

    public function isBlocked(): bool
    {
        return $this->card_status === self::CARD_STATUS_BLOCKED;
    }

    public function isUsed(): bool
    {
        return $this->card_status === self::CARD_STATUS_USED;
    }

    public function canCheckIn(): bool
    {
        return $this->isActive()
            && ! $this->isNotAttending()
            && $this->remaining_guests > 0;
    }

    public function markAsUsedIfFullyCheckedIn(): void
    {
        if ($this->remaining_guests <= 0 && $this->card_status === self::CARD_STATUS_ACTIVE) {
            $this->forceFill([
                'card_status' => self::CARD_STATUS_USED,
            ])->saveQuietly();
        }
    }
}