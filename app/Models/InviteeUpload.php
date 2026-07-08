<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class InviteeUpload extends Model
{
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
        'type_label',
        'status_label',
    ];

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

    public function getFileUrlAttribute(): ?string
    {
        if (blank($this->file_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->file_path);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::types()[$this->type] ?? ucfirst((string) $this->type);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statuses()[$this->status] ?? ucfirst((string) $this->status);
    }

    public function approve(?int $userId = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $userId,
            'approved_at' => now(),
            'rejected_at' => null,
        ])->save();
    }

    public function reject(?int $userId = null, ?string $note = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_REJECTED,
            'approved_by' => $userId,
            'approved_at' => null,
            'rejected_at' => now(),
            'admin_note' => $note,
        ])->save();
    }

    public function markPending(): void
    {
        $this->forceFill([
            'status' => self::STATUS_PENDING,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_at' => null,
        ])->save();
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