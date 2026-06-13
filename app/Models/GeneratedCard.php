<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GeneratedCard extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_GENERATING = 'generating';
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

    protected $appends = [
        'file_url',
        'card_url',
        'download_name',
        'status_label',
        'status_color',
    ];

    protected static function booted(): void
    {
        static::creating(function (GeneratedCard $generatedCard): void {
            if (blank($generatedCard->status)) {
                $generatedCard->status = self::STATUS_PENDING;
            }
        });
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_GENERATING => 'Generating',
            self::STATUS_GENERATED => 'Generated',
            self::STATUS_SENT => 'Sent',
            self::STATUS_FAILED => 'Failed',
        ];
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

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(Invitee::class);
    }

    public function cardTemplate(): BelongsTo
    {
        return $this->belongsTo(CardTemplate::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForEvent(Builder $query, int $eventId): Builder
    {
        return $query->where('event_id', $eventId);
    }

    public function scopeForInvitee(Builder $query, int $inviteeId): Builder
    {
        return $query->where('invitee_id', $inviteeId);
    }

    public function scopeForTemplate(Builder $query, int $cardTemplateId): Builder
    {
        return $query->where('card_template_id', $cardTemplateId);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeGenerating(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_GENERATING);
    }

    public function scopeGenerated(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_GENERATED);
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SENT);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getFileUrlAttribute(): ?string
    {
        if (blank($this->file_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->file_path);
    }

    public function getCardUrlAttribute(): ?string
    {
        return $this->file_url;
    }

    public function getDownloadNameAttribute(): string
    {
        $inviteeName = $this->invitee?->name ?: 'invitee';
        $serial = $this->invitee?->serial_number ?: $this->id;

        $safeName = Str::of($inviteeName)
            ->replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '')
            ->replaceMatches('/\s+/', '-')
            ->lower()
            ->toString();

        $extension = pathinfo((string) $this->file_path, PATHINFO_EXTENSION) ?: 'jpg';

        return "{$safeName}-{$serial}.{$extension}";
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statuses()[$this->status] ?? ucfirst((string) $this->status);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'gray',
            self::STATUS_GENERATING => 'warning',
            self::STATUS_GENERATED => 'success',
            self::STATUS_SENT => 'info',
            self::STATUS_FAILED => 'danger',
            default => 'gray',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Status Helpers
    |--------------------------------------------------------------------------
    */

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isGenerating(): bool
    {
        return $this->status === self::STATUS_GENERATING;
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

    public function fileExists(): bool
    {
        return filled($this->file_path)
            && Storage::disk('public')->exists($this->file_path);
    }

    public function belongsToEvent(int $eventId): bool
    {
        return (int) $this->event_id === (int) $eventId;
    }

    public function markAsPending(): void
    {
        $this->update([
            'status' => self::STATUS_PENDING,
        ]);
    }

    public function markAsGenerating(): void
    {
        $this->update([
            'status' => self::STATUS_GENERATING,
        ]);
    }

    public function markAsGenerated(?string $filePath = null): void
    {
        $data = [
            'status' => self::STATUS_GENERATED,
            'generated_at' => now(),
        ];

        if (filled($filePath)) {
            $data['file_path'] = $filePath;
        }

        $this->update($data);
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