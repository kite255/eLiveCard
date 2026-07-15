<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class InviteeUpload extends Model
{
    use HasFactory;

    public const TYPE_WISH = 'wish';
    public const TYPE_PHOTO = 'photo';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'event_id',
        'invitee_id',
        'type',
        'message',
        'file_path',
        'status',
        'approved_by',
        'approved_at',
        'rejected_at',
        'admin_note',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected $appends = [
        'file_url',
        'file_exists',
        'type_label',
        'status_label',
    ];

    protected static function booted(): void
    {
        static::creating(function (InviteeUpload $upload): void {
            $upload->status ??= self::STATUS_PENDING;
        });
    }

    public static function types(): array
    {
        return [
            self::TYPE_WISH => 'Wish',
            self::TYPE_PHOTO => 'Photo',
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
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

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopePhotos(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_PHOTO);
    }

    public function scopeWishes(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_WISH);
    }

    public function scopeForEvent(Builder $query, int $eventId): Builder
    {
        return $query->where('event_id', $eventId);
    }

    public function scopeForInvitee(Builder $query, int $inviteeId): Builder
    {
        return $query->where('invitee_id', $inviteeId);
    }

    public function getFileUrlAttribute(): ?string
    {
        if (! $this->hasStoredFile()) {
            return null;
        }

        return Storage::disk('public')->url($this->file_path);
    }

    public function getFileExistsAttribute(): bool
    {
        return $this->hasStoredFile();
    }

    public function getTypeLabelAttribute(): string
    {
        return self::types()[$this->type]
            ?? ucfirst(str_replace('_', ' ', (string) $this->type));
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statuses()[$this->status]
            ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    public function approve(?int $userId = null, ?string $note = null): bool
    {
        return $this->forceFill([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $userId ?? auth()->id(),
            'approved_at' => now(),
            'rejected_at' => null,
            'admin_note' => $note,
        ])->save();
    }

    public function reject(?int $userId = null, ?string $note = null): bool
    {
        return $this->forceFill([
            'status' => self::STATUS_REJECTED,
            'approved_by' => $userId ?? auth()->id(),
            'approved_at' => null,
            'rejected_at' => now(),
            'admin_note' => $note,
        ])->save();
    }

    public function markPending(): bool
    {
        return $this->forceFill([
            'status' => self::STATUS_PENDING,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_at' => null,
            'admin_note' => null,
        ])->save();
    }

    public function hasStoredFile(): bool
    {
        return filled($this->file_path)
            && Storage::disk('public')->exists($this->file_path);
    }

    public function deleteStoredFile(): bool
    {
        if (! $this->hasStoredFile()) {
            return false;
        }

        return Storage::disk('public')->delete($this->file_path);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isPhoto(): bool
    {
        return $this->type === self::TYPE_PHOTO;
    }

    public function isWish(): bool
    {
        return $this->type === self::TYPE_WISH;
    }
}
