<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class CardTemplate extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    /**
     * eLive Card standard template dimensions.
     *
     * The upload, designer, and generated output must all use the same logical
     * dimensions so placeholder positions and sizes remain WYSIWYG.
     */
    public const REQUIRED_TEMPLATE_WIDTH = 1080;
    public const REQUIRED_TEMPLATE_HEIGHT = 1465;

    public const DESIGNER_REFERENCE_WIDTH = self::REQUIRED_TEMPLATE_WIDTH;
    public const DESIGNER_REFERENCE_HEIGHT = self::REQUIRED_TEMPLATE_HEIGHT;

    protected $fillable = [
        'event_id',
        'name',
        'template_image',

        /*
        |--------------------------------------------------------------------------
        | Legacy / designer dimensions
        |--------------------------------------------------------------------------
        | width and height may already exist in older installations. They are kept
        | for backward compatibility and as a fallback when source dimensions
        | cannot be detected.
        */
        'width',
        'height',

        /*
        |--------------------------------------------------------------------------
        | Real uploaded source-image dimensions
        |--------------------------------------------------------------------------
        | Add these columns through a migration. New uploads should populate them.
        */
        'source_width',
        'source_height',

        'status',
    ];

    protected $casts = [
        'event_id' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'source_width' => 'integer',
        'source_height' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (CardTemplate $cardTemplate): void {
            if (blank($cardTemplate->status)) {
                $cardTemplate->status = self::STATUS_DRAFT;
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Keep source dimensions synchronized automatically
        |--------------------------------------------------------------------------
        | If a new local public-storage image is assigned and source dimensions
        | have not been set yet, detect them before the model is saved.
        |
        | This keeps the model resilient even if an upload flow forgets to set
        | source_width/source_height explicitly.
        */
        static::saving(function (CardTemplate $cardTemplate): void {
            if (blank($cardTemplate->template_image)) {
                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Enforce one eLive Card template dimension everywhere
            |--------------------------------------------------------------------------
            | UI validation is helpful, but model-level validation prevents another
            | controller, import, API, job, or future admin screen from saving an
            | unsupported template size.
            */
            [$sourceWidth, $sourceHeight] = $cardTemplate->detectSourceDimensions();

            /*
             * When editing a record without replacing the image, an already-known
             * source dimension can safely be used if storage detection is
             * temporarily unavailable.
             */
            $sourceWidth ??= (int) ($cardTemplate->source_width ?: 0);
            $sourceHeight ??= (int) ($cardTemplate->source_height ?: 0);

            if (
                $sourceWidth !== self::REQUIRED_TEMPLATE_WIDTH
                || $sourceHeight !== self::REQUIRED_TEMPLATE_HEIGHT
            ) {
                throw ValidationException::withMessages([
                    'template_image' => sprintf(
                        'The card template must be exactly %d × %d pixels.',
                        self::REQUIRED_TEMPLATE_WIDTH,
                        self::REQUIRED_TEMPLATE_HEIGHT,
                    ),
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Keep all stored dimensions synchronized
            |--------------------------------------------------------------------------
            */
            $cardTemplate->source_width = self::REQUIRED_TEMPLATE_WIDTH;
            $cardTemplate->source_height = self::REQUIRED_TEMPLATE_HEIGHT;
            $cardTemplate->width = self::DESIGNER_REFERENCE_WIDTH;
            $cardTemplate->height = self::DESIGNER_REFERENCE_HEIGHT;
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

    /**
     * Real source width in pixels.
     *
     * Uses the persisted source_width first and falls back to auto detection.
     */
    public function getSourceImageWidthAttribute(): int
    {
        if ((int) $this->source_width > 0) {
            return (int) $this->source_width;
        }

        [$width] = $this->detectSourceDimensions();

        return max(
            1,
            (int) ($width ?: $this->width ?: self::REQUIRED_TEMPLATE_WIDTH)
        );
    }

    /**
     * Real source height in pixels.
     *
     * Uses the persisted source_height first and falls back to auto detection.
     */
    public function getSourceImageHeightAttribute(): int
    {
        if ((int) $this->source_height > 0) {
            return (int) $this->source_height;
        }

        [, $height] = $this->detectSourceDimensions();

        return max(
            1,
            (int) ($height ?: $this->height ?: self::REQUIRED_TEMPLATE_HEIGHT)
        );
    }

    /**
     * Width used by the browser card designer.
     */
    public function getDesignerWidthAttribute(): int
    {
        return self::REQUIRED_TEMPLATE_WIDTH;
    }

    /**
     * Height used by the browser card designer.
     *
     * It always preserves the actual uploaded image aspect ratio.
     */
    public function getDesignerHeightAttribute(): int
    {
        return self::REQUIRED_TEMPLATE_HEIGHT;
    }

    /**
     * Scale factor from designer pixels to the actual generated image.
     *
     * Example:
     * With the fixed 1080 × 1465 template standard, the normal scale is 1.0.
     */
    public function getDesignerToSourceScaleAttribute(): float
    {
        return $this->source_image_width
            / max(1, self::REQUIRED_TEMPLATE_WIDTH);
    }

    /**
     * Convert a source aspect ratio to the corresponding designer height.
     */
    public static function calculateDesignerHeight(
        int $sourceWidth,
        int $sourceHeight,
        int $designerWidth = self::DESIGNER_REFERENCE_WIDTH,
    ): int {
        /*
         * Current eLive Card templates are fixed at 1080 × 1465.
         * Keep this method for compatibility with existing callers.
         */
        if ($designerWidth === self::DESIGNER_REFERENCE_WIDTH) {
            return self::DESIGNER_REFERENCE_HEIGHT;
        }

        $sourceWidth = max(1, $sourceWidth);
        $sourceHeight = max(1, $sourceHeight);
        $designerWidth = max(1, $designerWidth);

        return max(
            1,
            (int) round(
                $designerWidth * ($sourceHeight / $sourceWidth)
            )
        );
    }

    public static function hasAllowedDimensions(int $width, int $height): bool
    {
        return $width === self::REQUIRED_TEMPLATE_WIDTH
            && $height === self::REQUIRED_TEMPLATE_HEIGHT;
    }

    /**
     * Detect the real local image dimensions from Laravel's public disk.
     *
     * @return array{0:?int,1:?int}
     */
    public function detectSourceDimensions(): array
    {
        $path = $this->normalizedTemplateImagePath();

        if (! $path) {
            return [null, null];
        }

        try {
            if (! Storage::disk('public')->exists($path)) {
                return [null, null];
            }

            $fullPath = Storage::disk('public')->path($path);

            if (! is_file($fullPath)) {
                return [null, null];
            }

            $size = @getimagesize($fullPath);

            if (! is_array($size) || ! isset($size[0], $size[1])) {
                return [null, null];
            }

            $width = (int) $size[0];
            $height = (int) $size[1];

            if ($width <= 0 || $height <= 0) {
                return [null, null];
            }

            return [$width, $height];
        } catch (Throwable) {
            return [null, null];
        }
    }

    /**
     * Normalize template_image so it can be checked on the public disk.
     */
    public function normalizedTemplateImagePath(): ?string
    {
        if (blank($this->template_image)) {
            return null;
        }

        $path = trim((string) $this->template_image);

        /*
        |--------------------------------------------------------------------------
        | Remote/data URLs cannot be inspected through the local public disk.
        |--------------------------------------------------------------------------
        */
        if (
            str_starts_with($path, 'http://')
            || str_starts_with($path, 'https://')
            || str_starts_with($path, 'data:image/')
        ) {
            return null;
        }

        $path = ltrim($path, '/');

        if (str_starts_with($path, 'public/')) {
            $path = substr($path, 7);
        }

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        }

        return $path !== '' ? $path : null;
    }

    public function getTemplateImageUrlAttribute(): ?string
    {
        if (blank($this->template_image)) {
            return null;
        }

        $path = trim((string) $this->template_image);

        if (
            str_starts_with($path, 'http://')
            || str_starts_with($path, 'https://')
            || str_starts_with($path, 'data:image/')
        ) {
            return $path;
        }

        $path = ltrim($path, '/');

        if (str_starts_with($path, 'public/')) {
            $path = substr($path, 7);
        }

        if (str_starts_with($path, 'storage/')) {
            return asset($path);
        }

        return asset('storage/' . $path);
    }
}
