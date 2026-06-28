<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;

class Invitee extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Card Status Constants
    |--------------------------------------------------------------------------
    */

    public const CARD_STATUS_PENDING = 'pending';
    public const CARD_STATUS_ACTIVE = 'active';
    public const CARD_STATUS_CANCELLED = 'cancelled';
    public const CARD_STATUS_BLOCKED = 'blocked';
    public const CARD_STATUS_USED = 'used';

    /*
    |--------------------------------------------------------------------------
    | Check-in Status Constants
    |--------------------------------------------------------------------------
    */

    public const CHECK_IN_STATUS_NOT_CHECKED_IN = 'not_checked_in';
    public const CHECK_IN_STATUS_CHECKED_IN = 'checked_in';

    /*
    |--------------------------------------------------------------------------
    | RSVP Status Constants
    |--------------------------------------------------------------------------
    */

    public const RSVP_PENDING = 'pending';
    public const RSVP_ATTENDING = 'attending';
    public const RSVP_NOT_ATTENDING = 'not_attending';
    public const RSVP_MAYBE = 'maybe';

    public const RSVP_STATUS_PENDING = self::RSVP_PENDING;
    public const RSVP_STATUS_ATTENDING = self::RSVP_ATTENDING;
    public const RSVP_STATUS_NOT_ATTENDING = self::RSVP_NOT_ATTENDING;
    public const RSVP_STATUS_MAYBE = self::RSVP_MAYBE;

    /*
    |--------------------------------------------------------------------------
    | SMS Status Constants
    |--------------------------------------------------------------------------
    */

    public const SMS_STATUS_NOT_SENT = 'not_sent';
    public const SMS_STATUS_PENDING = 'pending';
    public const SMS_STATUS_SENT = 'sent';
    public const SMS_STATUS_DELIVERED = 'delivered';
    public const SMS_STATUS_FAILED = 'failed';

    /*
    |--------------------------------------------------------------------------
    | Communication Tracking Constants
    |--------------------------------------------------------------------------
    */

    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const MESSAGE_STATUS_NOT_SENT = 'not_sent';
    public const MESSAGE_STATUS_QUEUED = 'queued';
    public const MESSAGE_STATUS_SENT = 'sent';
    public const MESSAGE_STATUS_DELIVERED = 'delivered';
    public const MESSAGE_STATUS_READ = 'read';
    public const MESSAGE_STATUS_FAILED = 'failed';
    public const MESSAGE_STATUS_REPLIED = 'replied';

    /*
    |--------------------------------------------------------------------------
    | QR Settings
    |--------------------------------------------------------------------------
    */

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
        'confirmed_guests',

        'serial_number',
        'short_code',
        'qr_token',
        'qr_token_hash',
        'qr_code',
        'qr_code_path',

        'card_status',
        'generated_card_path',

        'rsvp_status',
        'rsvp_confirmed_at',
        'rsvp_token',

        'checked_in_count',
        'checked_in_at',
        'check_in_status',

        'sms_status',
        'sms_sent_at',
        'sms_message_id',
        'sms_error',

        'invitation_sms_status',
        'invitation_sms_sent_at',
        'reminder_sms_status',
        'reminder_sms_sent_at',
        'reminder_sms_error',
        'final_sms_status',
        'final_sms_sent_at',
        'last_sms_error',

        'last_sms_sent_at',
        'last_whatsapp_sent_at',
        'last_message_channel',
        'last_message_status',
        'last_reply_message',
        'last_reply_at',

        'first_opened_at',
        'last_opened_at',
        'open_count',
        'last_open_ip',
        'last_open_user_agent',
    ];

    protected $casts = [
        'allowed_guests' => 'integer',
        'confirmed_guests' => 'integer',
        'checked_in_count' => 'integer',

        'rsvp_confirmed_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'sms_sent_at' => 'datetime',
        'invitation_sms_sent_at' => 'datetime',
        'reminder_sms_sent_at' => 'datetime',
        'final_sms_sent_at' => 'datetime',
        'last_sms_sent_at' => 'datetime',
        'last_whatsapp_sent_at' => 'datetime',
        'last_reply_at' => 'datetime',

        'first_opened_at' => 'datetime',
        'last_opened_at' => 'datetime',
        'open_count' => 'integer',
    ];

    protected $appends = [
        'final_allowed_guests',
        'remaining_guests',
        'qr_code_url',
        'generated_card_url',
        'private_invitation_url',
        'rsvp_url',
        'is_checked_in',
        'check_in_status_label',
        'last_message_status_label',
        'last_message_channel_label',
        'has_opened_invitation',
        'open_status_label',
        'last_opened_human',
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

            if (
                Schema::hasColumn('invitees', 'check_in_status')
                && blank($invitee->check_in_status)
            ) {
                $invitee->check_in_status = self::CHECK_IN_STATUS_NOT_CHECKED_IN;
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

            if (
                Schema::hasColumn('invitees', 'last_message_status')
                && blank($invitee->last_message_status)
            ) {
                $invitee->last_message_status = self::MESSAGE_STATUS_NOT_SENT;
            }

            if ($invitee->allowed_guests === null) {
                $invitee->allowed_guests = 1;
            }

            if ($invitee->confirmed_guests === null) {
                $invitee->confirmed_guests = 0;
            }

            if ($invitee->checked_in_count === null) {
                $invitee->checked_in_count = 0;
            }

            if (Schema::hasColumn('invitees', 'open_count') && $invitee->open_count === null) {
                $invitee->open_count = 0;
            }
        });

        static::created(function (Invitee $invitee) {
            $invitee->generateQrCode();
        });
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

    public function cardType(): BelongsTo
    {
        return $this->belongsTo(CardType::class);
    }

    public function generatedCards(): HasMany
    {
        return $this->hasMany(GeneratedCard::class);
    }

    public function latestGeneratedCard(): HasOne
    {
        return $this->hasOne(GeneratedCard::class)->latestOfMany('generated_at');
    }

    public function latestSuccessfulGeneratedCard(): HasOne
    {
        return $this->hasOne(GeneratedCard::class)
            ->where('status', 'generated')
            ->latestOfMany('generated_at');
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    public function smsLogs(): HasMany
    {
        return $this->hasMany(SmsLog::class);
    }

    public function messageLogs(): HasMany
    {
        return $this->hasMany(MessageLog::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(InviteeConversation::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Options
    |--------------------------------------------------------------------------
    */

    public static function rsvpStatuses(): array
    {
        return [
            self::RSVP_PENDING => 'Pending',
            self::RSVP_ATTENDING => 'Attending',
            self::RSVP_NOT_ATTENDING => 'Not Attending',
            self::RSVP_MAYBE => 'Maybe',
        ];
    }

    public static function cardStatuses(): array
    {
        return [
            self::CARD_STATUS_PENDING => 'Pending',
            self::CARD_STATUS_ACTIVE => 'Active',
            self::CARD_STATUS_CANCELLED => 'Cancelled',
            self::CARD_STATUS_BLOCKED => 'Blocked',
            self::CARD_STATUS_USED => 'Used',
        ];
    }

    public static function checkInStatuses(): array
    {
        return [
            self::CHECK_IN_STATUS_NOT_CHECKED_IN => 'Not Checked In',
            self::CHECK_IN_STATUS_CHECKED_IN => 'Checked In',
        ];
    }

    public static function smsStatuses(): array
    {
        return [
            self::SMS_STATUS_NOT_SENT => 'Not Sent',
            self::SMS_STATUS_PENDING => 'Pending',
            self::SMS_STATUS_SENT => 'Sent',
            self::SMS_STATUS_DELIVERED => 'Delivered',
            self::SMS_STATUS_FAILED => 'Failed',
        ];
    }

    public static function communicationStatuses(): array
    {
        return [
            self::MESSAGE_STATUS_NOT_SENT => 'Not Sent',
            self::MESSAGE_STATUS_QUEUED => 'Queued',
            self::MESSAGE_STATUS_SENT => 'Sent',
            self::MESSAGE_STATUS_DELIVERED => 'Delivered',
            self::MESSAGE_STATUS_READ => 'Read',
            self::MESSAGE_STATUS_FAILED => 'Failed',
            self::MESSAGE_STATUS_REPLIED => 'Replied',
        ];
    }

    public static function communicationChannels(): array
    {
        return [
            self::CHANNEL_SMS => 'SMS',
            self::CHANNEL_WHATSAPP => 'WhatsApp',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Generators
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | RSVP Helpers
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | QR Helpers
    |--------------------------------------------------------------------------
    */

    public function generateQrCode(): void
    {
        $this->ensureQrIdentityExists();

        $qrUrl = $this->getQrTargetUrl();

        $folder = 'events/' . $this->event_id . '/qr-codes';
        $fileName = $this->serial_number . '.png';
        $path = $folder . '/' . $fileName;

        /*
         * Generate PNG QR codes using Endroid's PngWriter.
         *
         * This produces a PNG file that Intervention Image can read and place
         * on generated invitation cards.
         */
        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($qrUrl)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::High)
            ->size(self::QR_SIZE)
            ->margin(self::QR_MARGIN)
            ->build();

        Storage::disk('public')->put($path, $result->getString());

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

    public function getGeneratedCardPathAttribute($value): ?string
    {
        if (filled($value)) {
            return $value;
        }

        return $this->latestSuccessfulGeneratedCard?->file_path;
    }

    public function getGeneratedCardUrlAttribute(): ?string
    {
        $path = $this->generated_card_path;

        if (blank($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public function hasGeneratedCard(): bool
    {
        if (filled($this->generated_card_path)) {
            return true;
        }

        if ($this->relationLoaded('latestSuccessfulGeneratedCard')) {
            return filled($this->latestSuccessfulGeneratedCard?->file_path);
        }

        return $this->generatedCards()
            ->where('status', 'generated')
            ->whereNotNull('file_path')
            ->exists();
    }

    public function scopeMissingGeneratedCard($query)
    {
        return $query
            ->where(function ($query) {
                $query
                    ->whereNull('generated_card_path')
                    ->orWhere('generated_card_path', '');
            })
            ->whereDoesntHave('generatedCards', function ($query) {
                $query
                    ->where('status', 'generated')
                    ->whereNotNull('file_path');
            });
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

    /*
    |--------------------------------------------------------------------------
    | SMS Helpers
    |--------------------------------------------------------------------------
    */

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

    public function updateSmsStatusByType(
        string $smsType,
        string $status,
        ?string $messageId = null,
        ?string $error = null
    ): void {
        $data = [
            'sms_status' => $status,
            'sms_message_id' => $messageId,
            'sms_error' => $error,
        ];

        if (in_array($status, [self::SMS_STATUS_SENT, self::SMS_STATUS_DELIVERED], true)) {
            $data['sms_sent_at'] = now();
        }

        if ($smsType === SmsLog::TYPE_INVITATION) {
            $data['invitation_sms_status'] = $status;

            if (in_array($status, [self::SMS_STATUS_SENT, self::SMS_STATUS_DELIVERED], true)) {
                $data['invitation_sms_sent_at'] = now();
            }
        }

        if (in_array($smsType, [SmsLog::TYPE_RSVP_PENDING_REMINDER, SmsLog::TYPE_ATTENDING_REMINDER], true)) {
            $data['reminder_sms_status'] = $status;
            $data['reminder_sms_error'] = $error;

            if (in_array($status, [self::SMS_STATUS_SENT, self::SMS_STATUS_DELIVERED], true)) {
                $data['reminder_sms_sent_at'] = now();
                $data['reminder_sms_error'] = null;
            }
        }

        if ($smsType === SmsLog::TYPE_EVENT_DAY_REMINDER) {
            $data['final_sms_status'] = $status;

            if (in_array($status, [self::SMS_STATUS_SENT, self::SMS_STATUS_DELIVERED], true)) {
                $data['final_sms_sent_at'] = now();
            }
        }

        if ($status === self::SMS_STATUS_FAILED) {
            $data['last_sms_error'] = $error;
        } else {
            $data['last_sms_error'] = null;
        }

        $this->forceFill($data)->saveQuietly();

        $this->markLastSmsCommunication($status, $messageId, $error);
    }

    /*
    |--------------------------------------------------------------------------
    | Communication Tracking Helpers
    |--------------------------------------------------------------------------
    */

    public function markLastSmsCommunication(
        string $status = self::MESSAGE_STATUS_SENT,
        ?string $messageId = null,
        ?string $error = null
    ): void {
        $data = [
            'last_message_channel' => self::CHANNEL_SMS,
            'last_message_status' => $status,
        ];

        if (in_array($status, [self::MESSAGE_STATUS_SENT, self::MESSAGE_STATUS_DELIVERED, self::SMS_STATUS_SENT, self::SMS_STATUS_DELIVERED], true)) {
            $data['last_sms_sent_at'] = now();
        }

        if (Schema::hasColumn('invitees', 'sms_message_id')) {
            $data['sms_message_id'] = $messageId;
        }

        if (Schema::hasColumn('invitees', 'sms_error')) {
            $data['sms_error'] = $status === self::MESSAGE_STATUS_FAILED ? $error : null;
        }

        $this->forceFill($data)->saveQuietly();
    }

    public function markLastWhatsAppCommunication(
        string $status = self::MESSAGE_STATUS_SENT,
        ?string $providerMessageId = null,
        ?array $providerResponse = null
    ): void {
        $data = [
            'last_message_channel' => self::CHANNEL_WHATSAPP,
            'last_message_status' => $status,
        ];

        if (in_array($status, [self::MESSAGE_STATUS_SENT, self::MESSAGE_STATUS_DELIVERED, self::MESSAGE_STATUS_READ], true)) {
            $data['last_whatsapp_sent_at'] = now();
        }

        $this->forceFill($data)->saveQuietly();
    }

    public function saveIncomingWhatsAppReply(string $message, ?string $fromPhone = null): void
    {
        $this->forceFill([
            'last_message_channel' => self::CHANNEL_WHATSAPP,
            'last_message_status' => self::MESSAGE_STATUS_REPLIED,
            'last_reply_message' => $message,
            'last_reply_at' => now(),
        ])->saveQuietly();
    }

    public function hasReplied(): bool
    {
        return filled($this->last_reply_message) || filled($this->last_reply_at);
    }

    public function hasWhatsappBeenSent(): bool
    {
        return filled($this->last_whatsapp_sent_at)
            || $this->last_message_channel === self::CHANNEL_WHATSAPP;
    }

    public function hasSmsBeenSent(): bool
    {
        return filled($this->last_sms_sent_at)
            || filled($this->sms_sent_at)
            || in_array($this->sms_status, [self::SMS_STATUS_SENT, self::SMS_STATUS_DELIVERED], true);
    }

    /*
    |--------------------------------------------------------------------------
    | Invitee Page Open Tracking Helpers
    |--------------------------------------------------------------------------
    */

    public function recordInvitationOpen(?string $ipAddress = null, ?string $userAgent = null): void
    {
        $data = [];

        if (Schema::hasColumn('invitees', 'first_opened_at')) {
            $data['first_opened_at'] = $this->first_opened_at ?: now();
        }

        if (Schema::hasColumn('invitees', 'last_opened_at')) {
            $data['last_opened_at'] = now();
        }

        if (Schema::hasColumn('invitees', 'open_count')) {
            $data['open_count'] = ((int) ($this->open_count ?? 0)) + 1;
        }

        if (Schema::hasColumn('invitees', 'last_open_ip')) {
            $data['last_open_ip'] = $ipAddress;
        }

        if (Schema::hasColumn('invitees', 'last_open_user_agent')) {
            $data['last_open_user_agent'] = $userAgent ? Str::limit($userAgent, 1000, '') : null;
        }

        if (! empty($data)) {
            $this->forceFill($data)->saveQuietly();
        }
    }

    public function hasOpenedInvitation(): bool
    {
        return filled($this->first_opened_at)
            || filled($this->last_opened_at)
            || ((int) ($this->open_count ?? 0)) > 0;
    }

    /*
    |--------------------------------------------------------------------------
    | Check-in Helpers
    |--------------------------------------------------------------------------
    */

    public function canCheckIn(int $guests = 1): bool
    {
        $guests = max(1, $guests);

        if ($this->card_status !== self::CARD_STATUS_ACTIVE) {
            return false;
        }

        return $this->remaining_guests >= $guests;
    }

    public function markCheckedIn(int $guests = 1): void
    {
        $guests = max(1, $guests);

        $data = [
            'checked_in_count' => (int) ($this->checked_in_count ?? 0) + $guests,
            'checked_in_at' => $this->checked_in_at ?? now(),
        ];

        if (Schema::hasColumn('invitees', 'check_in_status')) {
            $data['check_in_status'] = self::CHECK_IN_STATUS_CHECKED_IN;
        }

        $this->forceFill($data)->saveQuietly();

        $this->markAsUsedIfFullyCheckedIn();
    }

    public function markAsUsedIfFullyCheckedIn(): void
    {
        if ($this->remaining_guests <= 0 && $this->card_status === self::CARD_STATUS_ACTIVE) {
            $this->forceFill([
                'card_status' => self::CARD_STATUS_USED,
            ])->saveQuietly();
        }
    }

    public function resetCheckIn(): void
    {
        $data = [
            'checked_in_count' => 0,
            'checked_in_at' => null,
            'card_status' => self::CARD_STATUS_ACTIVE,
        ];

        if (Schema::hasColumn('invitees', 'check_in_status')) {
            $data['check_in_status'] = self::CHECK_IN_STATUS_NOT_CHECKED_IN;
        }

        $this->forceFill($data)->saveQuietly();
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getFinalAllowedGuestsAttribute(): int
    {
        return (int) (
            $this->allowed_guests
            ?? $this->cardType?->allowed_people
            ?? $this->cardType?->allowed_guests
            ?? $this->cardType?->guest_count
            ?? 1
        );
    }

    public function getRemainingGuestsAttribute(): int
    {
        $allowed = (int) ($this->final_allowed_guests ?? 0);
        $checkedIn = (int) ($this->checked_in_count ?? 0);

        return max($allowed - $checkedIn, 0);
    }

    public function getIsCheckedInAttribute(): bool
    {
        return (int) ($this->checked_in_count ?? 0) > 0;
    }

    public function getRsvpStatusLabelAttribute(): string
    {
        return self::rsvpStatuses()[$this->rsvp_status]
            ?? ucfirst(str_replace('_', ' ', (string) $this->rsvp_status));
    }

    public function getCardStatusLabelAttribute(): string
    {
        return self::cardStatuses()[$this->card_status]
            ?? ucfirst(str_replace('_', ' ', (string) $this->card_status));
    }

    public function getCheckInStatusLabelAttribute(): string
    {
        $status = $this->check_in_status
            ?? (
                $this->is_checked_in
                    ? self::CHECK_IN_STATUS_CHECKED_IN
                    : self::CHECK_IN_STATUS_NOT_CHECKED_IN
            );

        return self::checkInStatuses()[$status]
            ?? ucfirst(str_replace('_', ' ', (string) $status));
    }

    public function getSmsStatusLabelAttribute(): string
    {
        return self::smsStatuses()[$this->sms_status]
            ?? ucfirst(str_replace('_', ' ', (string) $this->sms_status));
    }

    public function getLastMessageStatusLabelAttribute(): string
    {
        return self::communicationStatuses()[$this->last_message_status]
            ?? ucfirst(str_replace('_', ' ', (string) $this->last_message_status));
    }

    public function getLastMessageChannelLabelAttribute(): string
    {
        if (blank($this->last_message_channel)) {
            return 'None';
        }

        return self::communicationChannels()[$this->last_message_channel]
            ?? ucfirst((string) $this->last_message_channel);
    }

    public function getHasOpenedInvitationAttribute(): bool
    {
        return $this->hasOpenedInvitation();
    }

    public function getOpenStatusLabelAttribute(): string
    {
        return $this->hasOpenedInvitation() ? 'Opened' : 'Not Opened';
    }

    public function getLastOpenedHumanAttribute(): string
    {
        return $this->last_opened_at
            ? $this->last_opened_at->diffForHumans()
            : 'Never opened';
    }

    /*
    |--------------------------------------------------------------------------
    | Status Helpers
    |--------------------------------------------------------------------------
    */

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
}