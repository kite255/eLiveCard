<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedCard extends Model
{
    public const STATUS_GENERATED = 'generated';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'event_id',
        'invitee_id',
        'card_template_id',
        'file_path',
        'status',
        'generated_at',
        'sent_at',
    ];

    protected $casts = [
        'event_id' => 'integer',
        'invitee_id' => 'integer',
        'card_template_id' => 'integer',
        'generated_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (GeneratedCard $generatedCard): void {
            if (blank($generatedCard->status)) {
                $generatedCard->status = self::STATUS_GENERATED;
            }

            if (blank($generatedCard->generated_at)) {
                $generatedCard->generated_at = now();
            }
        });
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_GENERATED => 'Generated',
            self::STATUS_SENT => 'Sent',
            self::STATUS_FAILED => 'Failed',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(Invitee::class);
    }

    public function cardTemplate(): BelongsTo
    {
        return $this->belongsTo(CardTemplate::class);
    }

    public function getFileUrlAttribute(): ?string
    {
        if (blank($this->file_path)) {
            return null;
        }

        return asset('storage/' . $this->file_path);
    }

    public function isGenerated(): bool
    {
        return $this->status === self::STATUS_GENERATED;
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function markAsSent(): void
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
        ]);
    }
}