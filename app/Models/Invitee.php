<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class Invitee extends Model
{
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
        'qr_token',
        'qr_token_hash',
        'qr_code_path',
        'card_status',
        'rsvp_status',
        'rsvp_confirmed_at',
        'checked_in_count',
        'checked_in_at',
    ];

    protected $casts = [
        'rsvp_confirmed_at' => 'datetime',
        'checked_in_at' => 'datetime',
    ];

    protected $appends = [
        'final_allowed_guests',
        'remaining_guests',
    ];

    protected static function booted(): void
    {
        static::creating(function (Invitee $invitee) {
            if (blank($invitee->serial_number)) {
                do {
                    $serialNumber = 'ELV-' . now()->format('Y') . '-' . strtoupper(Str::random(6));
                } while (self::where('serial_number', $serialNumber)->exists());

                $invitee->serial_number = $serialNumber;
            }

            if (blank($invitee->qr_token)) {
                do {
                    $rawToken = Str::random(64);
                } while (self::where('qr_token', $rawToken)->exists());

                $invitee->qr_token = $rawToken;
                $invitee->qr_token_hash = hash('sha256', $rawToken);
            }

            if (blank($invitee->card_status)) {
                $invitee->card_status = 'pending';
            }

            if (blank($invitee->rsvp_status)) {
                $invitee->rsvp_status = 'pending';
            }

            if ($invitee->checked_in_count === null) {
                $invitee->checked_in_count = 0;
            }
        });

        static::created(function (Invitee $invitee) {
            $invitee->generateQrCode();
        });
    }

    public function generateQrCode(): void
    {
        if (blank($this->qr_token) || blank($this->serial_number)) {
            return;
        }

        $verifyUrl = url('/gate/verify/' . $this->qr_token);

        $folder = 'events/' . $this->event_id . '/qr-codes';
        $fileName = $this->serial_number . '.svg';
        $path = $folder . '/' . $fileName;

        Storage::disk('public')->put(
            $path,
            QrCode::format('svg')
                ->size(300)
                ->margin(2)
                ->generate($verifyUrl)
        );

        $this->forceFill([
            'qr_code_path' => $path,
        ])->saveQuietly();
    }

    public function regenerateQrCode(): void
    {
        if (filled($this->qr_code_path) && Storage::disk('public')->exists($this->qr_code_path)) {
            Storage::disk('public')->delete($this->qr_code_path);
        }

        $this->generateQrCode();
    }

    public function getQrCodeUrlAttribute(): ?string
    {
        if (blank($this->qr_code_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->qr_code_path);
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

    public function getFinalAllowedGuestsAttribute(): int
    {
        return $this->allowed_guests ?? $this->cardType?->allowed_people ?? 1;
    }

    public function getRemainingGuestsAttribute(): int
    {
        return max(0, $this->final_allowed_guests - $this->checked_in_count);
    }
}