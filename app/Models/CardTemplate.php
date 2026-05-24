<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CardTemplate extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'event_id',
        'name',
        'template_image',
        'width',
        'height',
        'status',
    ];

    protected $casts = [
        'event_id' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (CardTemplate $cardTemplate): void {
            if (blank($cardTemplate->status)) {
                $cardTemplate->status = self::STATUS_DRAFT;
            }
        });
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_ARCHIVED => 'Archived',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function placeholders(): HasMany
    {
        return $this->hasMany(CardTemplatePlaceholder::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function visiblePlaceholders(): HasMany
    {
        return $this->hasMany(CardTemplatePlaceholder::class)
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function generatedCards(): HasMany
    {
        return $this->hasMany(GeneratedCard::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ARCHIVED);
    }

    public function scopeForEvent(Builder $query, int $eventId): Builder
    {
        return $query->where('event_id', $eventId);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    public function belongsToEvent(int $eventId): bool
    {
        return (int) $this->event_id === (int) $eventId;
    }

    public function hasTemplateImage(): bool
    {
        return filled($this->template_image);
    }

    public function hasPlaceholders(): bool
    {
        return $this->placeholders()->exists();
    }

    public function getTemplateImageUrlAttribute(): ?string
    {
        if (blank($this->template_image)) {
            return null;
        }

        return asset('storage/' . $this->template_image);
    }
}